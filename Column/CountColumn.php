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
 * Class CountColumn
 *
 */
class CountColumn extends BaseColumn
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

    /**
     * {@inheritdoc}
     */
    public function getClassName()
    {
        return 'count';
    }
    
    /**
     * {@inheritdoc}
     */
    public function isOverride()
    {
        return true;
    }
    
    /**
     * {@inheritdoc}
     */
    public function customQuery($qb, $em, $metadata)
    {
        $fieldInformation = $metadata->getAssociationMapping($this->getProperty());
        dump($fieldInformation);
        $mainIdent = $metadata->getSingleIdentifierFieldName();
        $qb2 = $em->createQueryBuilder();
        $qb2->select('COUNT(trg.id)')
            ->from($fieldInformation['targetEntity'], 'trg')
            ->where('trg.' . $fieldInformation['mappedBy'] . ' = ' . $metadata->getTableName() . '.' . $mainIdent);
            
        $qb->addSelect('(' . $qb2->getDQL() . ') AS ' . $this->getProperty());
    }

    /**
     * {@inheritdoc}
     */
    public function setDefaults()
    {
        parent::setDefaults();

        $property = $this->getProperty();
        
        $this->setData($property);
    }
}
