<?php

namespace RavuAlHemio\IcingaStatusBundle\Controller;

use Doctrine\DBAL\Connection;
use RavuAlHemio\IcingaStatusBundle\Utility\StateNameUtils;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class ServiceMatrixController extends Controller
{
    const SERVICE_GROUPS_QUERY = '
        SELECT
            icsg.servicegroup_object_id,
            icsg.alias
        FROM
            icinga_servicegroups AS icsg
            INNER JOIN icinga_objects AS icsgo ON icsgo.object_id = icsg.servicegroup_object_id
        WHERE
            icsgo.is_active = 1
        ORDER BY
            icsg.alias
    ';

    const HOSTS_QUERY = '
        SELECT
            ich.host_object_id,
            ich.display_name,
            ichs.current_state
        FROM
            icinga_hosts AS ich
            INNER JOIN icinga_objects AS icho ON icho.object_id = ich.host_object_id
            INNER JOIN icinga_hoststatus AS ichs ON ichs.host_object_id = ich.host_object_id
        WHERE
            icho.is_active = 1
        ORDER BY
            ich.display_name
    ';

    const SERVICE_COUNTS_QUERY = '
        SELECT
            icsg.servicegroup_object_id,
            ich.host_object_id,
            COUNT(*) AS service_count,
            MAX(icss.current_state) AS worst_state
        FROM
            icinga_servicegroups AS icsg
            INNER JOIN icinga_objects AS icsgo ON icsgo.object_id = icsg.servicegroup_object_id
            INNER JOIN icinga_servicegroup_members AS icsgm ON icsgm.servicegroup_id = icsg.servicegroup_id
            INNER JOIN icinga_services AS ics ON ics.service_object_id = icsgm.service_object_id
            INNER JOIN icinga_servicestatus AS icss ON icss.service_object_id = ics.service_object_id
            INNER JOIN icinga_objects AS icso ON icso.object_id = ics.service_object_id
            INNER JOIN icinga_hosts AS ich ON ich.host_object_id = ics.host_object_id
            INNER JOIN icinga_object AS icho ON icho.object_id = ich.host_object_id
        WHERE
            icsgo.is_active = 1
            AND icso.is_active = 1
            AND icho.is_active = 1
        GROUP BY
            icsg.servicegroup_object_id,
            ich.host_object_id
    ';

    public function serviceMatrixAction()
    {
        $strConnName = $this->container->getParameter('icingastatus.database_connection');
        /** @var Connection $objConn */
        $objConn = $this->get("doctrine.dbal.{$strConnName}_connection");

        $arrServiceGroupOIDs = [];
        $arrServiceGroupOIDsToNames = [];
        $arrHostOIDs = [];
        $arrHostOIDsToNamesAndStates = [];
        $arrHostOIDsToServiceGroupOIDsToStateInfo = [];

        // obtain service groups
        $objStmt = $objConn->executeQuery(static::SERVICE_GROUPS_QUERY);
        while (($arrRow = $objStmt->fetch()))
        {
            $intServiceGroupOID = (int)$arrRow['servicegroup_object_id'];
            $arrServiceGroupOIDs[] = $intServiceGroupOID;
            $arrServiceGroupOIDsToNames[$intServiceGroupOID] = $arrRow['alias'];
        }

        // obtain hosts
        $objStmt = $objConn->executeQuery(static::HOSTS_QUERY);
        while (($arrRow = $objStmt->fetch()))
        {
            $intHostOID = (int)$arrRow['host_object_id'];
            $arrHostOIDs[] = $intHostOID;
            $arrHostOIDsToNamesAndStates[$intHostOID] = [
                'name' => $arrRow['display_name'],
                'state' => StateNameUtils::getServiceStateCSSClassName($arrRow['current_state'])
            ];
        }

        // prepare cross join
        foreach ($arrHostOIDs as $intHostOID)
        {
            $arrServiceGroupOIDsToWorstStates = [];
            foreach ($arrServiceGroupOIDs as $intServiceGroupOID)
            {
                $arrServiceGroupOIDsToStateInfo[$intServiceGroupOID] = [
                    'worst_state' => 'none',
                    'service_count' => 0
                ];
            }

            $arrHostOIDsToServiceGroupOIDsToStateInfo[$intHostOID] = $arrServiceGroupOIDsToWorstStates;
        }

        // perform cross join (manually)
        $objStmt = $objConn->executeQuery(static::SERVICE_COUNTS_QUERY);
        while (($arrRow = $objStmt->fetch()))
        {
            $intServiceGroupOID = (int)$arrRow['servicegroup_object_id'];
            $intHostOID = (int)$arrRow['host_object_id'];

            $arrHostOIDsToServiceGroupOIDsToStateInfo[$intHostOID][$intServiceGroupOID] = [
                'worst_state' => StateNameUtils::getServiceStateCSSClassName((int)$arrRow['worst_state']),
                'service_count' => (int)$arrRow['service_count']
            ];
        }

        return $this->render('@RavuAlHemioIcingaStatus/servicematrix.html.twig', [
            'host_oids' => $arrHostOIDs,
            'hosts' => $arrHostOIDsToNamesAndStates,
            'service_group_oids' => $arrServiceGroupOIDs,
            'service_group_names' => $arrServiceGroupOIDsToNames,
            'hosts_to_services_to_info' => $arrHostOIDsToServiceGroupOIDsToStateInfo
        ]);
    }
}
