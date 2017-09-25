<?php

namespace RavuAlHemio\IcingaStatusBundle\Controller;

use Doctrine\DBAL\Connection;
use RavuAlHemio\IcingaStatusBundle\Utility\StateNameUtils;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class StatusListController extends Controller
{
    const LAST_STATUS_UPDATE_QUERY = '
        SELECT
            MIN(UNIX_TIMESTAMP(status_update_time)) AS last_status_update_timestamp
        FROM
            icinga_programstatus
    ';

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
                ichs.has_been_checked AS host_checked,
                NULL AS service_checked,
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
                LEFT OUTER JOIN (
                    SELECT DISTINCT ON (object_id) object_id, comment_data
                    FROM icinga_acknowledgements
                    ORDER BY object_id, entry_time DESC
                ) AS icha ON icha.object_id = ich.host_object_id AND ichs.problem_has_been_acknowledged = 1
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
                ichs.has_been_checked AS host_checked,
                icss.has_been_checked AS service_checked,
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
                LEFT OUTER JOIN (
                    SELECT DISTINCT ON (object_id) object_id, comment_data
                    FROM icinga_acknowledgements
                    ORDER BY object_id, entry_time DESC
                ) AS icsa ON icsa.object_id = ics.service_object_id AND icss.problem_has_been_acknowledged = 1
                LEFT OUTER JOIN icinga_scheduleddowntime AS icssd ON icssd.object_id = ics.service_object_id AND icssd.is_in_effect = 1
            WHERE
                icho.is_active = 1
                AND icso.is_active = 1
        ) AS innerquery
    ';

    const ORDER_BY_HOST_FIRST = '
        ORDER BY
            badness_level DESC,
            CASE WHEN service_name IS NULL THEN 0 ELSE 1 END,
            current_state DESC,
            host_name,
            service_name
    ';
    const ORDER_BY_SERVICE_FIRST = '
        ORDER BY
            badness_level DESC,
            CASE WHEN service_name IS NULL THEN 0 ELSE 1 END,
            current_state DESC,
            service_name,
            host_name
    ';

    public function statusListAction(Request $objRequest)
    {
        $strSort = $objRequest->query->get('sort');
        $strFullOutput = $objRequest->query->get('fullOutput');
        $blnFullOutput = !empty($strFullOutput);

        $strQuery = static::STATUS_QUERY;
        if ($strSort === 'service')
        {
            $strQuery .= static::ORDER_BY_SERVICE_FIRST;
        }
        else
        {
            $strQuery .= static::ORDER_BY_HOST_FIRST;
        }

        $strConnName = $this->container->getParameter('icingastatus.database_connection');
        /** @var Connection $objConn */
        $objConn = $this->get("doctrine.dbal.{$strConnName}_connection");

        $objStmt = $objConn->executeQuery(static::LAST_STATUS_UPDATE_QUERY);
        $blnUpToDate = $false;
        if (($arrRow = $objStmt->fetch()))
        {
            $intLastUpdateUnix = $arrRow['last_status_update_timestamp'];
            $intNowUnix = time();
            if ($intNowUnix - $intLastUpdateUnix <= 60)
            {
                // last status update within 1min
                $blnUpToDate = $true;
            }
        }

        $objStmt = $objConn->executeQuery($strQuery);
        $arrViewEntries = [];
        while (($arrRow = $objStmt->fetch()))
        {
            $arrViewEntry = [];

            $strHostState = $arrRow['host_checked']
                ? StateNameUtils::getHostStateCSSClassName($arrRow['host_state'])
                : StateNameUtils::PENDING_STATE_CSS_CLASS_NAME;
            $strAcknowledgement = $arrRow['someone_is_on_it'] ? 'acknowledged' : 'unacknowledged';

            $arrViewEntry['short_output'] = htmlspecialchars($arrRow['output']);
            $arrViewEntry['full_output'] = htmlspecialchars(
                $arrRow['output'] . "\n" . str_replace("\\n", "\n", $arrRow['long_output'])
            );
            $arrViewEntry['output'] = $blnFullOutput ? $arrViewEntry['full_output'] : $arrViewEntry['short_output'];

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
                $arrViewEntry['comment'] = htmlspecialchars($arrRow['downtime_comment']);
            }

            if ($arrRow['service_name'] === null)
            {
                $arrViewEntry['type'] = 'host';
                $arrViewEntry['status'] = "host-{$strHostState} {$strAcknowledgement}";
                $arrViewEntry['status_abbr'] = $arrRow['host_checked']
                    ? StateNameUtils::getHostStateDisplayAbbr($arrRow['host_state'])
                    : StateNameUtils::PENDING_STATE_DISPLAY_ABBR;
                $arrViewEntry['name'] = htmlspecialchars($arrRow['host_name']);
            }
            else
            {
                $strServiceState = $arrRow['service_checked']
                    ? StateNameUtils::getServiceStateCSSClassName($arrRow['service_state'])
                    : StateNameUtils::PENDING_STATE_CSS_CLASS_NAME;

                $arrViewEntry['type'] = 'service';
                $arrViewEntry['status'] = "service-{$strServiceState} host-{$strHostState} {$strAcknowledgement}";
                $arrViewEntry['status_abbr'] = $arrRow['service_checked']
                    ? StateNameUtils::getServiceStateDisplayAbbr($arrRow['service_state'])
                    : StateNameUtils::PENDING_STATE_DISPLAY_ABBR;
                $arrViewEntry['name'] = htmlspecialchars($arrRow['host_name']) . ' &middot; ' . htmlspecialchars($arrRow['service_name']);
            }

            $arrViewEntries[] = $arrViewEntry;
        }

        return $this->render('@RavuAlHemioIcingaStatus/icingastatus.html.twig', [
            'isUpToDate' => $blnUpToDate,
            'entries' => $arrViewEntries
        ]);
    }

    protected static function formatDateTimeDelta(\DateTimeInterface $dtmNow, \DateTimeInterface $dtmThen)
    {
        $dinDelta = $dtmThen->diff($dtmNow);

        if ($dinDelta->days >= 2)
        {
            // output the date instead
            return $dtmThen->format('d M H:i');
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
