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
use Exception;

/**
 * Class BooleanColumn
 *
 */
class MultiSelectColumn extends BaseColumn
{
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

    public function jsonSerialize()
    {
        $data = parent::jsonSerialize();
        $data['title'] = "<input type='checkbox' name='multiselect_checkall' class='multiselect_checkall' />";
        $data['data'] = null;
        $data['searchable'] = false;
        $data['sortable'] = false;
        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function getClassName()
    {
        return 'multiselect';
    }

    /**
     * {@inheritdoc}
     */
    public function setOptions(array $options)
    {
        if (array_key_exists('render', $options)) {
            if (null == $options['render']) {
                throw new Exception('The render option can not be null.');
            }
        }

        parent::setOptions($options);
    }

    /**
     * {@inheritdoc}
     */
    public function setDefaults()
    {
        parent::setDefaults();

        $this->setRender('render_bulkaction');
    }
}
