<?php

namespace Icinga\Module\Vspheredb\Web\Widget;

use dipl\Html\BaseHtmlElement;
use dipl\Html\Icon;
use dipl\Html\Link;
use dipl\Translation\TranslationHelper;
use dipl\Web\Url;
use Icinga\Module\Vspheredb\Db;
use Icinga\Module\Vspheredb\Web\Table\Objects\ObjectsTable;
use Zend_Db_Select as ZfSelect;

class Summaries extends BaseHtmlElement
{
    use TranslationHelper;

    protected $tag = 'div';

    protected $defaultAttributes = [
        'class' => 'object-summaries',
    ];

    /** @var \Zend_Db_Select */
    protected $query;

    protected $stats;

    /** @var \Zend_Db_Adapter_Abstract */
    protected $db;

    /** @var Url */
    protected $baseUrl;

    protected $wantsPowerState = false;

    public function __construct(ObjectsTable $table, Db $db, Url $baseUrl)
    {
        $this->baseUrl = $baseUrl;
        $this->db = $db->getDbAdapter();
        $this->setQueryFromTable($table);
        $this->addColumn('o.overall_status', ['gray', 'green', 'yellow', 'red']);
        if ($table->hasColumn('runtime_power_state')) {
            $column = $table
                ->getAvailableColumn('runtime_power_state')
                ->getMainColumnExpression();

            $this->addColumn($column, [
                'poweredOn',
                'poweredOff',
                'unknown',
                'standby',
                'suspended',
            ]);

            $this->wantsPowerState = true;
        }

        $this->applyUrlFilters($table);
    }

    protected function applyUrlFilters(ObjectsTable $table)
    {
        foreach ($this->baseUrl->getParams()->toArray() as $param) {
            if ($table->hasColumn($param[0])) {
                $key = $param[1];
                if ($key === 'poweredon') {
                    $key = 'poweredOn';
                }
                if ($key === 'poweredoff') {
                    $key = 'poweredOff';
                }
                $table->getQuery()->where($param[0] . ' = ?', $key);
            }
        }
    }

    protected function setQueryFromTable(ObjectsTable $table)
    {
        $query = clone($table->getQuery());
        $query->reset(ZfSelect::LIMIT_COUNT);
        $query->reset(ZfSelect::LIMIT_OFFSET);
        $query->reset(ZfSelect::COLUMNS);
        $query->reset(ZfSelect::ORDER);

        // This works, but is not as general-purpose as it should be
        if (count($query->getPart(ZfSelect::GROUP)) > 0) {
            $query = $query->getAdapter()->select()->from([
                'o' => $query->columns('o.overall_status')
            ], []);
        }

        $this->query = $query;
    }

    protected function addColumn($column, $variants)
    {
        $columns = [];
        foreach ($variants as $value) {
            $columns[$this->makeColumnAlias($value)] = $this->countFiltered($column, $value);
        }
        $this->query->columns($columns);
    }

    protected function makeColumnAlias($column)
    {
        return 'cnt_' . strtolower(preg_replace('/^.+?\./', '', $column));
    }

    protected function countFiltered($column, $value)
    {
        return "SUM(CASE WHEN $column = '$value' THEN 1 ELSE 0 END)";
    }

    protected function stats()
    {
        if ($this->stats === null) {
            $this->stats = $this->db->fetchRow($this->query);
        }

        return $this->stats;
    }

    public function addPowerState()
    {
        return $this;
    }

    protected function createSummaryLink($value, $property)
    {
        $stats = $this->stats();
        $count = (int) $stats->{"cnt_$value"};

        if ($count === 0) {
            return null;
        }

        $class = $value;
        $chosen = $this->baseUrl->getParam($property) === $value;
        if ($chosen) {
            $class = "$class chosen";
        }

        return Link::create(
            $count,
            $this->baseUrl->without($property),
            $chosen ? null : [$property => $value],
            ['class' => $class]
        );
    }

    protected function addSummaryLinks($column, $variants)
    {
        foreach ($variants as $value) {
            $this->add($this->createSummaryLink($value, $column));
        }
    }

    protected function assemble()
    {
        $this->setSeparator(' ');
        $this->add([Icon::create('ok'), $this->translate('Status')]);
        $this->addSummaryLinks('overall_status', [
            'red',
            'yellow',
            'gray',
            'green'
        ]);
        if ($this->wantsPowerState) {
            $this->add([Icon::create('off'), $this->translate('Power')]);
            $this->addSummaryLinks('runtime_power_state', [
                'poweredon',
                'poweredoff',
                'unknown',
                'standby',
                'suspended'
            ]);
        }
    }
}
