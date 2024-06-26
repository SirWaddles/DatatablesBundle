<?php

/**
 * This file is part of the TommyGNRDatatablesBundle package.
 *
 * (c) Tom Corrigan <https://github.com/tommygnr/DatatablesBundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TommyGNR\DatatablesBundle\Datatable;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\HttpFoundation\Response;
use TommyGNR\DatatablesBundle\Datatable\View\AbstractDatatableView;
use Exception;

/**
 * Class DatatableData
 */
class DatatableData implements DatatableDataInterface
{
    /**
     * @var array
     */
    protected $requestParams;

    /**
     * @var AbstractDatatableView
     */
    protected $datatable;

    /**
     * @var ClassMetadata
     */
    protected $metadata;

    /**
     * @var EntityManager
     */
    protected $em;

    /**
     * @var Serializer
     */
    protected $serializer;

    /**
     * @var DatatableQuery
     */
    protected $datatableQuery;

    /**
     * The field name of the identifier/primary key.
     *
     * @var mixed
     */
    protected $rootEntityIdentifier;

    /**
     * @var array
     */
    protected $selectColumns;

    /**
     * @var array
     */
    protected $allColumns;

    /**
     * @var array
     */
    protected $joins;

    /**
     * Constructor.
     *
     * @param array          $requestParams  All GET params
     * @param ClassMetadata  $metadata       A ClassMetadata instance
     * @param EntityManager  $em             A EntityManager instance
     * @param Serializer     $serializer     A Serializer instance
     * @param DatatableQuery $datatableQuery A DatatableQuery instance
     */
    public function __construct(array $requestParams, AbstractDatatableView $datatable, EntityManagerInterface $doctrine, Serializer $serializer)
    {
        $this->requestParams = $requestParams;
        $this->datatable = $datatable;
        $this->metadata = $doctrine->getClassMetadata($datatable->getEntity());
        $this->em = $doctrine;
        $this->serializer = $serializer;

        $this->datatableQuery = new DatatableQuery($requestParams, $this->metadata, $this->em, $datatable);
        $identifiers = $this->metadata->getIdentifierFieldNames();
        $this->rootEntityIdentifier = array_shift($identifiers);
        $this->selectColumns = array();
        $this->allColumns = array();
        $this->joins = array();

        $this->prepareColumns();
    }

    /**
     * Add an entry to the joins[] array.
     *
     * @param array $join
     *
     * @return $this
     */
    private function addJoin(array $join)
    {
        $this->joins[] = $join;

        return $this;
    }

    /**
     * Add an entry to the selectColumns[] array.
     *
     * @param ClassMetadata $metadata        A ClassMetadata instance
     * @param string        $column          The name of the column
     * @param null|string   $columnTableName The name of the column table
     *
     * @throws Exception
     * @return $this
     */
    private function addSelectColumn(ClassMetadata $metadata, $column, $columnTableName = null)
    {
        if ($column === null) return $this;
        if (in_array($column, $metadata->getFieldNames())) {
            $this->selectColumns[($columnTableName ?: $metadata->getTableName())][] = $column;
        } else {
            throw new Exception('Column '.$column.' not found in entity.');
        }

        return $this;
    }

    /**
     * Set associations in joins[].
     *
     * @param array         $associationParts An array of the association parts
     * @param ClassMetadata $metadata         A ClassMetadata instance
     * @param null|string   $columnTableName  The name of the column table
     *
     * @return $this
     */
    private function setAssociations(array $associationParts, ClassMetadata $metadata, $rootPath = null, $parentTableAlias = null)
    {
        if ($rootPath == null) {
            $rootPath = implode('.', $associationParts);
        }

        $column = array_shift($associationParts);

        if ($metadata->hasAssociation($column) === true) {
            $targetClass = $metadata->getAssociationTargetClass($column);
            $targetMeta = $this->em->getClassMetadata($targetClass);
            $targetTableName = $targetMeta->getTableName();
            $targetIdentifiers = $targetMeta->getIdentifierFieldNames();
            $targetRootIdentifier = array_shift($targetIdentifiers);
            $parentTableAlias = $parentTableAlias ?: $metadata->getTableName();
            $targetTableAlias = $parentTableAlias.'_'.$targetTableName.'_'.$column;
            if (!array_key_exists($targetTableAlias, $this->selectColumns)) {
                $this->addSelectColumn($targetMeta, $targetRootIdentifier, $targetTableAlias);

                $this->addJoin(
                    array(
                        'source' => $parentTableAlias.'.'.$column,
                        'target' => $targetTableAlias,
                    )
                );
            }

            $this->setAssociations($associationParts, $targetMeta, $rootPath, $targetTableAlias);
        } else {
            $targetIdentifiers = $metadata->getIdentifierFieldNames();
            $targetRootIdentifier = array_shift($targetIdentifiers);

            if ($column !== $targetRootIdentifier) {
                $this->addSelectColumn($metadata, $column, $parentTableAlias);
                $this->datatableQuery->addResolvedTableAlias($rootPath, $parentTableAlias, $column);
            }

            $this->allColumns[] = $parentTableAlias.'.'.$column;
        }

        return $this;
    }

    /**
     * Prepare selectColumns[], allColumns[] and joins[].
     *
     * @return $this
     */
    private function prepareColumns()
    {
        // start with the tableName and the primary key e.g. 'fos_user' and 'id'
        $this->addSelectColumn($this->metadata, $this->rootEntityIdentifier);

        foreach ($this->datatable->getColumns() as $column) {
            // association delimiter found (e.g. 'posts.comments.title')?
            if (strstr($column->getProperty(), '.') !== false) {
                $array = explode('.', $column->getProperty());
                $this->setAssociations($array, $this->metadata);
            } else {
                // no association found
                if ($column !== $this->rootEntityIdentifier) {
                    $this->addSelectColumn($this->metadata, $column->getProperty());
                }

                $this->allColumns[] = $this->metadata->getTableName().'.'.$column->getProperty();
            }
        }

        return $this;
    }

    /**
     * Convert a property into it's table alias
     */
    public function getTableAlias($property)
    {
        $associationParts = explode('.', $property);
        $tableAlias = $this->metadata->getTableName();

        while (count($associationParts) > 1) {
            $column = array_shift($associationParts);
            $found = false;
            foreach ($this->joins as $join) {
                if ($join['source'] == $tableAlias . '.' . $column) {
                    $tableAlias = $join['target'];
                    $found = true;
                }
            }
            if (!$found) {
                throw new Exception("Could not find join " . $column . ". Might not exist");
            }
        }

        return $tableAlias . "." . $associationParts[0];
    }

    /**
     * Build query.
     *
     * @return $this
     */
    private function buildQuery()
    {
        //Set columns
        $this->datatableQuery->setSelectFrom($this->selectColumns);
        $this->datatableQuery->setAllColumns($this->allColumns);

        foreach ($this->datatable->getColumns() as $idx => $column) {
            $column->customQuerySettings($this->datatableQuery, $this, $idx);
        }

        $this->datatableQuery->setLimit();
        $this->datatableQuery->setOrderBy();

        $this->datatableQuery->setJoins($this->joins);
        $this->datatableQuery->setLeftJoins($this->datatableQuery->getQb());
        $this->datatableQuery->setWhere($this->datatableQuery->getQb());
        $this->datatableQuery->setWhereCallbacks($this->datatableQuery->getQb());

        return $this;
    }

    private function processResults($paginator, $fieldName)
    {
        // In some strange circumstances, Doctrine will return duplicate results
        // when using the array hydration.
        // It seems like it would be very difficult to fix upstream

        $data = [];

        foreach ($paginator as $item) {
            if (isset($item[$fieldName])) {
                $data[] = $item[$fieldName];
            } else {
                $data[] = $item;
            }
        }

        if (empty($data)) return $data;
        if (!isset($data[0]['id'])) return $data;

        $unique = [];
        foreach ($data as $item) {
            $unique[$item['id']] = $item;
        }

        return array_values($unique);
    }

    /**
     * {@inheritdoc}
     */
    public function getResponse()
    {
        $this->buildQuery();
        $paginator = new Paginator($this->datatableQuery->execute(), true);

        $output = array(
            'draw' => (int) $this->requestParams['draw'],
            'recordsTotal' => $this->datatableQuery->getCountAllResults($this->rootEntityIdentifier),
            'recordsFiltered' => $paginator->count(),
            'data' => [],
            'columnFilterChoices' => $this->getColumnFilterChoices(),
        );

        $fieldName = 't_' . $this->metadata->getTableName();

        $output['data'] = $this->processResults($paginator, $fieldName);

        array_walk_recursive($output, function (&$value, $key) {
            if (is_string($value)) {
                $value = htmlentities($value);
            }
        });

        $json = $this->serializer->serialize($output, 'json');
        $response = new Response($json);
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

    public function getColumnFilterChoices()
    {
        $result = [];

        foreach ($this->datatable->getColumns() as $column) {
            if ($column->isFilterSeeded()) {
                $result[$column->getProperty()] = $this->datatableQuery->getColumnValues($column, clone $this->datatableQuery->getQb());
            }
        }

        return $result;
    }

    /**
     * Add a callback function.
     *
     * @param string $callback
     *
     * @throws Exception
     * @return DatatableData
     */
    public function addWhereBuilderCallback($callback)
    {
        if (!is_callable($callback)) {
            throw new Exception('The callback argument must be callable.');
        }

        $this->datatableQuery->addCallback($callback);

        return $this;
    }
}
