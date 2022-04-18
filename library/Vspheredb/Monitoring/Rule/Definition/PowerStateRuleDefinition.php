<?php

namespace Icinga\Module\Vspheredb\Monitoring\Rule\Definition;

use Icinga\Date\DateFormatter;
use Icinga\Module\Vspheredb\DbObject\BaseDbObject;
use Icinga\Module\Vspheredb\DbObject\HostQuickStats;
use Icinga\Module\Vspheredb\DbObject\HostSystem;
use Icinga\Module\Vspheredb\DbObject\VirtualMachine;
use Icinga\Module\Vspheredb\DbObject\VmQuickStats;
use Icinga\Module\Vspheredb\Monitoring\CheckPluginState;
use Icinga\Module\Vspheredb\Monitoring\Rule\Enum\MonitoringStateTrigger;
use Icinga\Module\Vspheredb\Monitoring\Rule\Enum\ObjectType;
use Icinga\Module\Vspheredb\Monitoring\Rule\Settings;
use Icinga\Module\Vspheredb\Monitoring\SingleCheckResult;
use InvalidArgumentException;

class PowerStateRuleDefinition extends MonitoringRuleDefinition
{
    public const SUPPORTED_OBJECT_TYPES = [
        ObjectType::HOST_SYSTEM,
        ObjectType::VIRTUAL_MACHINE,
    ];

    public static function getIdentifier(): string
    {
        return 'PowerState';
    }

    public function getLabel(): string
    {
        return $this->translate('Power State');
    }

    public function checkObject(BaseDbObject $object, Settings $settings): array
    {
        if ($object instanceof VirtualMachine) {
            $what = 'Virtual Machine';
            if ($object->get('template') === 'y') {
                return [
                    new SingleCheckResult(new CheckPluginState(), 'This is a VM template')
                ];
            }
        } elseif ($object instanceof HostSystem) {
            $what = 'Host System';
        } else {
            $what = 'Object';
        }

        $powerState = $object->get('runtime_power_state');
        if ($powerState === 'poweredOn') {
            $state = new CheckPluginState(CheckPluginState::OK);
        } else {
            $state = MonitoringStateTrigger::getMonitoringState($settings->get("trigger_on_$powerState"));
        }
        $message = $this->getStatusMessageForPowerState($powerState, $what);

        $results = [
            new SingleCheckResult($state, $message)
        ];

        if ($powerState === 'poweredOn') {
            $uptimeState = new CheckPluginState();
            if ($object instanceof HostSystem) {
                $stats = HostQuickStats::loadFor($object);
            } else {
                assert($object instanceof VirtualMachine);
                $stats = VmQuickStats::loadFor($object);
            }
            $results[] = new SingleCheckResult($uptimeState, sprintf(
                'System booted %s ago',
                DateFormatter::formatDuration($stats->get('uptime'))
            ));
            $minUptime = $settings->get('warning_for_uptime_less_than_seconds');
            if ($stats->get('uptime') < $minUptime) {
                $uptimeState->raiseState(CheckPluginState::WARNING);
            }
            $minUptime = $settings->get('critical_for_uptime_less_than_seconds');
            if ($stats->get('uptime') < $minUptime) {
                $uptimeState->raiseState(CheckPluginState::CRITICAL);
            }
            $maxUptime = $settings->get('warning_for_uptime_greater_than_days');
            if ($stats->get('uptime') > $maxUptime * 86400) {
                $uptimeState->raiseState(CheckPluginState::WARNING);
            }
            $maxUptime = $settings->get('critical_for_uptime_greater_than_days');
            if ($stats->get('uptime') > $maxUptime * 86400) {
                $uptimeState->raiseState(CheckPluginState::CRITICAL);
            }
        }

        return $results;
    }

    protected function getStatusMessageForPowerState($state, $what): string
    {
        switch ($state) {
            case 'poweredOff':
                return "$what has been powered off";
            case 'suspended':
                return "$what has been suspended";
            case 'unknown':
                return "$what power state is unknown, might be disconnected";
            case 'poweredOn':
                return "$what is powered on";
        }

        throw new InvalidArgumentException("'$state' is not a known power state");
    }

    public function getInternalDefaults(): array
    {
        return [
            'trigger_on_poweredOff ' => MonitoringStateTrigger::RAISE_CRITICAL,
            'trigger_on_suspended'   => MonitoringStateTrigger::RAISE_CRITICAL,
            'trigger_on_unknown'     => MonitoringStateTrigger::RAISE_UNKNOWN,
            'warning_for_uptime_less_than' => 900,
        ];
    }

    public function getParameters(): array
    {
        return [
            'trigger_on_poweredOff' => ['state_trigger', [
                'label' => $this->translate('When powered off'),
            ]],
            'trigger_on_suspended' => ['state_trigger', [
                'label' => $this->translate('When suspended'),
            ]],
            'trigger_on_unknown' => ['state_trigger', [
                'label'       => $this->translate('When unknown'),
                'description' => $this->translate('Might be disconnected'),
            ]],
            'warning_for_uptime_less_than' => ['number', [
                'label'       => $this->translate('Raise WARNING for uptime less than'),
                'description' => $this->translate('Please provide the uptime in seconds'),
            ]],
            'critical_for_uptime_less_than' => ['number', [
                'label'       => $this->translate('Raise CRITICAL for uptime less than'),
                'description' => $this->translate('Please provide the uptime in seconds'),
            ]],
            'warning_for_uptime_greater_than_days' => ['number', [
                'label'       => $this->translate('Raise WARNING for uptime greater than'),
                'description' => $this->translate('Please provide the uptime in days'),
            ]],
            'critical_for_uptime_greater_than_days' => ['number', [
                'label'       => $this->translate('Raise CRITICAL for uptime greater than'),
                'description' => $this->translate('Please provide the uptime in days'),
            ]],
        ];
    }
}
