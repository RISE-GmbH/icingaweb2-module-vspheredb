<?php

namespace Icinga\Module\Vspheredb\Polling;

use Icinga\Module\Vspheredb\PerformanceData\PerformanceSet\PerformanceSet;
use JsonSerializable;

class PerfDataSet implements JsonSerializable
{
    /** @var int */
    protected $vCenterId;

    /** @var string */
    protected $measurementName;

    /** @var array */
    protected $counters;

    /** @var array */
    protected $requiredInstances;

    /**
     * ServerInfo constructor.
     * @param int $vCenterId
     * @param string $measurementName
     * @param array $counters
     * @param array $requiredInstances
     */
    public function __construct($vCenterId, $measurementName, array $counters, array $requiredInstances)
    {
        $this->vCenterId = $vCenterId;
        $this->measurementName = $measurementName;
        $this->counters = $counters;
        $this->requiredInstances = $requiredInstances;
    }

    public static function fromPlainObject($object)
    {
        return new static(
            $object->vCenterId,
            $object->measurementName,
            (array) $object->counters,
            (array) $object->instances
        );
    }

    public static function fromPerformanceSet($vCenterId, PerformanceSet $set)
    {
        return new static(
            $vCenterId,
            $set->getMeasurementName(),
            $set->getCounters(),
            $set->getRequiredInstances()
        );
    }

    /**
     * @return int
     */
    public function getVCenterId()
    {
        return $this->vCenterId;
    }

    /**
     * @return string
     */
    public function getMeasurementName()
    {
        return $this->measurementName;
    }

    /**
     * @return array
     */
    public function getCounters()
    {
        return $this->counters;
    }

    /**
     * @return array
     */
    public function getRequiredInstances()
    {
        return $this->requiredInstances;
    }

    public function jsonSerialize()
    {
        return ((object) [
            'vCenterId'       => $this->vCenterId,
            'measurementName' => $this->getMeasurementName(),
            'counters'        => $this->getCounters(),
            'instances'       => $this->getRequiredInstances(),
        ]);
    }
}
