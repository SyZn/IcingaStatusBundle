<?php

namespace RavuAlHemio\IcingaStatusBundle\Utility;

class StateNameUtils
{
    public static function getServiceStateCSSClassName($intStateCode)
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

    public static function getServiceStateDisplayAbbr($intStateCode)
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

    public static function getHostStateCSSClassName($intStateCode)
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

    public static function getHostStateDisplayAbbr($intStateCode)
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
}
