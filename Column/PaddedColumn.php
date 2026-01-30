<?php

/**
 * This file is part of the TommyGNRDatatablesBundle package.
 *
 * (c) Tom Corrigan <https://github.com/tommygnr/DatatablesBundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TommyGNR\DatatablesBundle\Column;

use TommyGNR\DatatablesBundle\Column\AbstractColumn as BaseColumn;
use TommyGNR\DatatablesBundle\Datatable\DatatableQuery;
use TommyGNR\DatatablesBundle\Datatable\DatatableData;
use Exception;

/**
 * Class BooleanColumn
 *
 */
class PaddedColumn extends BaseColumn
{
    private $prefix;

    private $padLength;

    /**
     * Constructor.
     *
     * @param null|string $property An entity's property
     *
     * @throws Exception
     */
    public function __construct($property = null)
    {
        if (null == $property) {
            throw new Exception("The entity's property can not be null.");
        }

        parent::__construct($property);
    }

    public function jsonSerialize(): mixed
    {
        $data = parent::jsonSerialize();
        $data['prefix'] = $this->getPrefix();
        $data['width'] = $this->getPadLength();
        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function getClassName()
    {
        return 'padded';
    }

    /**
     * {@inheritdoc}
     */
    public function setOptions(array $options)
    {
        if (array_key_exists('prefix', $options)) {
            $this->setPrefix($options['prefix']);
        }
        if (array_key_exists('padLength', $options)) {
            $this->setPadLength($options['padLength']);
        }
        parent::setOptions($options);
    }

    /**
     * {@inheritdoc}
     */
    public function setDefaults()
    {
        parent::setDefaults();
        $this->setPrefix('COL-');
        $this->setPadLength(5);
    }

    public function getPrefix()
    {
        return $this->prefix;
    }

    public function setPrefix(string $prefix)
    {
        $this->prefix = $prefix;
        return $this;
    }

    public function getPadLength()
    {
        return $this->padLength;
    }

    public function setPadLength(int $padLength)
    {
        $this->padLength = $padLength;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function customQuerySettings(DatatableQuery $query, DatatableData $data, int $index)
    {
        $qb = $query->getQb();
        $fieldRef = $data->getTableAlias($this->getProperty());
        $padLength = $this->getPadLength();
        $prefix = $this->getPrefix();
        $fieldName = "CONCAT_WS('-', '$prefix', LPAD($fieldRef, $padLength, '0'))";

        $columns = $query->getAllColumns();
        $columns[$index] = $fieldName;
        $query->setAllColumns($columns);
    }
}
