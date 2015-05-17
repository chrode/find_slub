<?php
namespace Slub\FindSlub\Slots;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with TYPO3 source code.
 *
 * The TYPO3 project - inspiring people to share!
 */
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use Solarium\QueryType\Select\Result\Document;


/**
 * Slot implementation before the
 *
 * @category    Slots
 * @package     TYPO3
 */
class EnrichSolrResult {

    /**
     * Contains the settings of the current extension
     *
     * @var array
     * @api
     */
    protected $settings;

    /**
     * @var \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface
     */
    protected $configurationManager;

    /**
     * @param \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface $configurationManager
     * @return void
     */
    public function injectConfigurationManager(ConfigurationManagerInterface $configurationManager) {
        $this->configurationManager = $configurationManager;
        $this->settings = $this->configurationManager->getConfiguration(ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS);
    }

    /**
     * Slot to enrich finds detail view
     *
     * @param array &$assignments
     */
    public function detail(&$assignments) {

        $assignments['enriched'] = array('fields' => array());

        $document = $assignments['document'];
        /* @var $document Document */

        if($document) {

            $fields = $document->getFields();

            foreach ($this->settings['enrich']['detail'] as $enrichment) {

                $field_data = '';

                $check_fields = is_array($fields[$enrichment['check_field']]) ? $fields[$enrichment['check_field']] : array($fields[$enrichment['check_field']]);

                foreach ($check_fields as $check_field) {

                    if (preg_match($enrichment['check_pattern'], $check_field, $matches) === 1) {

                        $field_data = $matches[1];
                    }
                }

                // TEMP: Get RSN from PPN
                if (($enrichment['check_field'] === 'rsn') && ($field_data === '')) {
                    $field_data = $this->getRSNfromPPN($fields);
                }

                if (strlen($field_data) > 0) {

                    $enriched = json_decode(file_get_contents(sprintf($enrichment['ws'], $field_data)), true);
                    $assignments['enriched']['fields'] = array_merge($assignments['enriched']['fields'], $enriched);
                }
            }

        }

    }

    /**
     * Resolves PPNs from Solr to PSN
     *
     * @param array $fields Fields returned from Solr
     * @return string
     */
    private function getRSNfromPPN($fields) {

        $rsn = '';

        if(strlen($fields['record_id']) > 0) {

            $opts = array('http' =>
                array(
                    'method'  => 'POST',
                    'header'  => 'Content-type: application/json',
                    'content' => json_encode(
                        array(
                            'getRSNfromPPN' => array(
                                'PPN' => $fields['record_id']
                            )
                        )
                    )
                )
            );

            $context  = stream_context_create($opts);

            $result = json_decode(file_get_contents('http://primoproxy.slub-dresden.de/cgi-bin/LiberoProxy.pl', false, $context), true);

            $rsn = $result['answer']['RSN'];

        }

        return $rsn;

    }

    /**
     * Slot to enrich finds detail view
     *
     * @param array &$resultSet
     */
    public function index(&$resultSet) {

    }


}
