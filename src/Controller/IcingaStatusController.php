<?php

namespace RavuAlHemio\IcingaStatusBundle\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class IcingaStatusController extends Controller
{
    const STATUS_QUERY = '
        SELECT
            *
        FROM (
            SELECT
                ich.display_name AS host_name,
                NULL AS service_name,
                ichs.current_state AS host_state,
                NULL AS service_state,
                ichs.current_state AS current_state,
                CASE
                    WHEN ichs.current_state > 0 AND icha.comment_data IS NULL AND ichsd.comment_data IS NULL THEN 2
                    WHEN ichs.current_state > 0 THEN 1
                    ELSE 0
                END AS badness_level,
                ichs.last_state_change AS last_change,
                ichs.output AS output,
                ichs.long_output AS long_output,
                icha.comment_data AS ack_comment,
                ichsd.comment_data AS downtime_comment,
                CASE WHEN icha.comment_data IS NULL AND ichsd.comment_data IS NULL THEN 0 ELSE 1 END AS someone_is_on_it
            FROM
                icinga_hosts AS ich
                INNER JOIN icinga_objects AS icho ON icho.object_id = ich.host_object_id
                INNER JOIN icinga_hoststatus AS ichs ON ichs.host_object_id = ich.host_object_id
                LEFT OUTER JOIN icinga_acknowledgements AS icha ON icha.object_id = ich.host_object_id AND ichs.problem_has_been_acknowledged = 1
                LEFT OUTER JOIN icinga_scheduleddowntime AS ichsd ON ichsd.object_id = ich.host_object_id AND ichsd.is_in_effect = 1
            WHERE
                icho.is_active = 1

            UNION ALL

            SELECT
                ich.display_name AS host_name,
                ics.display_name AS service_name,
                ichs.current_state AS host_state,
                icss.current_state AS service_state,
                icss.current_state AS current_state,
                CASE
                    WHEN icss.current_state > 0 AND ichs.current_state > 0 THEN 1
                    WHEN icss.current_state > 0 AND icsa.comment_data IS NULL AND icssd.comment_data IS NULL THEN 2
                    WHEN icss.current_state > 0 THEN 1
                    ELSE 0
                END AS badness_level,
                icss.last_state_change AS last_change,
                icss.output AS output,
                icss.long_output AS long_output,
                icsa.comment_data AS ack_comment,
                icssd.comment_data AS downtime_comment,
                CASE WHEN icsa.comment_data IS NULL AND icssd.comment_data IS NULL AND ichs.current_state = 0 THEN 0 ELSE 1 END AS someone_is_on_it
            FROM
                icinga_services AS ics
                INNER JOIN icinga_objects AS icso ON icso.object_id = ics.service_object_id
                INNER JOIN icinga_hosts AS ich ON ich.host_object_id = ics.host_object_id
                INNER JOIN icinga_hoststatus AS ichs ON ichs.host_object_id = ich.host_object_id
                INNER JOIN icinga_objects AS icho ON icho.object_id = ics.host_object_id
                INNER JOIN icinga_servicestatus AS icss ON icss.service_object_id = ics.service_object_id
                LEFT OUTER JOIN icinga_acknowledgements AS icsa ON icsa.object_id = ics.service_object_id AND icss.problem_has_been_acknowledged = 1
                LEFT OUTER JOIN icinga_scheduleddowntime AS icssd ON icssd.object_id = ics.service_object_id AND icssd.is_in_effect = 1
            WHERE
                icho.is_active = 1
                AND icso.is_active = 1
        ) AS innerquery
        ORDER BY
            badness_level DESC,
            someone_is_on_it,
            CASE WHEN service_name IS NULL THEN 0 ELSE 1 END,
            current_state DESC,
            host_name,
            service_name
    ';

    public function showStatusAction()
    {
        $strConnName = $this->container->getParameter('icingastatus.database_connection');
        /** @var Connection $objConn */
        $objConn = $this->get("doctrine.dbal.{$strConnName}_connection");

        $objStmt = $objConn->executeQuery(static::STATUS_QUERY);
        $arrViewEntries = [];
        while ($arrRow = $objStmt->fetch())
        {
            $arrViewEntry = [];

            $strHostState = static::getHostStateName($arrRow['host_state']);
            $strAcknowledgement = $arrRow['someone_is_on_it'] ? 'acknowledged' : 'unacknowledged';

            $arrViewEntry['output'] = htmlspecialchars($arrRow['output']);
            $arrViewEntry['full_output'] = htmlspecialchars($arrRow['output'] . "\n" . $arrRow['long_output']);

            $dtzLocal = new \DateTimeZone(date_default_timezone_get());
            $dtmLastChange = \DateTime::createFromFormat('Y-m-d H:i:s', $arrRow['last_change'], $dtzLocal);
            $dtmNow = new \DateTime('now', $dtzLocal);
            $arrViewEntry['status_duration'] = static::formatDateTimeDelta($dtmNow, $dtmLastChange);

            $arrViewEntry['comment'] = null;
            if ($arrRow['ack_comment'])
            {
                $arrViewEntry['comment'] = htmlspecialchars($arrRow['ack_comment']);
            }
            else if ($arrRow['downtime_comment'])
            {
                $arrViewEntry['comment'] = htmlspecialchars($arrRow['ack_comment']);
            }

            if ($arrRow['service_name'] === null)
            {
                $arrViewEntry['type'] = 'host';
                $arrViewEntry['status'] = "host-{$strHostState} {$strAcknowledgement}";
                $arrViewEntry['status_abbr'] = static::getHostStateAbbr($arrRow['host_state']);
                $arrViewEntry['name'] = htmlspecialchars($arrRow['host_name']);
            }
            else
            {
                $strServiceState = static::getServiceStateName($arrRow['service_state']);

                $arrViewEntry['type'] = 'service';
                $arrViewEntry['status'] = "service-{$strServiceState} host-{$strHostState} {$strAcknowledgement}";
                $arrViewEntry['status_abbr'] = static::getServiceStateAbbr($arrRow['service_state']);
                $arrViewEntry['name'] = htmlspecialchars($arrRow['host_name']) . ' &middot; ' . htmlspecialchars($arrRow['service_name']);
            }

            $arrViewEntries[] = $arrViewEntry;
        }

        return $this->render('@RavuAlHemioIcingaStatus/icingastatus.html.twig', [
            'entries' => $arrViewEntries
        ]);
    }

    protected static function getServiceStateName($intStateCode)
    {
        switch ($intStateCode)
        {
            case 0:
                return 'ok';
            case 1:
                return 'warning';
            case 2:
                return 'critical';
            case 3:
                return 'unknown';
            default:
                return 'invalid-code';
        }
    }

    protected static function getServiceStateAbbr($intStateCode)
    {
        switch ($intStateCode)
        {
            case 0:
                return 'OK';
            case 1:
                return 'warn';
            case 2:
                return 'crit';
            case 3:
                return 'unknown';
            default:
                return '???';
        }
    }

    protected static function getHostStateName($intStateCode)
    {
        switch ($intStateCode)
        {
            case 0:
                return 'up';
            case 1:
                return 'warning';
            case 2:
            case 3:
                return 'down';
            default:
                return 'invalid-code';
        }
    }

    protected static function getHostStateAbbr($intStateCode)
    {
        switch ($intStateCode)
        {
            case 0:
                return 'up';
            case 1:
                return 'warn';
            case 2:
            case 3:
                return 'down';
            default:
                return '???';
        }
    }

    protected static function formatDateTimeDelta(\DateTimeInterface $dtmNow, \DateTimeInterface $dtmThen)
    {
        $dinDelta = $dtmThen->diff($dtmNow);

        if ($dinDelta->days >= 2)
        {
            // output the date instead
            return $dtmNow->format('d M H:i');
        }

        $strDelta = '';
        if ($dinDelta->d > 0)
        {
            $strDelta = $dinDelta->format('%d d %h:%I');
        }
        else if ($dinDelta->h > 0)
        {
            $strDelta = $dinDelta->format('%h:%I');
        }
        else if ($dinDelta->m > 0)
        {
            $strDelta = $dinDelta->format('%I min');
        }
        else
        {
            $strDelta = $dinDelta->format('%s s');
        }

        if ($dinDelta->invert)
        {
            $strDelta = "in $strDelta";
        }

        return $strDelta;
    }
}
