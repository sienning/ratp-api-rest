<?php
namespace ApiBundle\Services\Api;

use ApiBundle\Helper\NamesHelper;
use ApiBundle\Helper\NamesHelperClass;
use GuzzleHttp\Client;

class TrafficService extends ApiService implements ApiDataInterface
{
    const ENTRYPOINT_IXXI = 'http://apixha.ixxi.net/APIX?cmd=getTrafficSituation&category=all' .
    '&networkType=all&withText=true&apixFormat=json';
    const ENTRYPOINT_RATP = 'http://www.ratp.fr/meteo/ajax/data';

    /**
     * @var string $apiKey
     */
    private $apiKey;

    /**
     * @var int $resultTtl
     */
    private $resultTtl;

    /**
     * TrafficService constructor.
     * We override parent constructor to inject curl result ttl and apikey.
     *
     * @param $ttl
     * @param $resultTtl
     * @param $apiKey
     */
    public function __construct($ttl, $resultTtl, $apiKey)
    {
        $this->apiKey    = $apiKey;
        $this->resultTtl = $resultTtl;

        parent::__construct($ttl);
    }

    /**
     * @param $method
     * @return mixed
     */
    public function get($method)
    {
        return parent::getData($method);
    }

    /**
     * @return array
     */
    protected function getAll()
    {
        $ratpData = $this->getDataFromRatp();
        $ixxiData = $this->getDataFromIxxi();

        $data = $this->mergeDataSources($ratpData, $ixxiData);

        return $data;
    }

    /**
     * @param $ratp
     * @param $ixxi
     * @return array
     */
    private function mergeDataSources($ratp, $ixxi)
    {
        // merge only RER C, D and E
        $allowedRers = [
            'C',
            'D',
            'E'
        ];

        foreach ($allowedRers as $allowedRer) {
            if (isset($ixxi['rers'][$allowedRer])) {
                $rer = $ixxi['rers'][$allowedRer];
                ksort($rer);

                $first_event = current($rer);

                $information = [
                    'line'    => $allowedRer,
                    'slug'    => NamesHelper::statusSlug($first_event['typeName']),
                    'title'   => $first_event['typeName'],
                    'message' => $first_event['message']
                ];
            } else {
                $information = [
                    'line'    => $allowedRer,
                    'slug'    => 'normal',
                    'title'   => 'Trafic normal',
                    'message' => 'Trafic normal sur l\'ensemble de la ligne.'
                ];
            }
            $tmpRers[$allowedRer] = $information;
        }

        ksort($tmpRers);

        foreach ($tmpRers as $rer) {
            $ratp['rers'][] = $rer;
        }

        return $ratp;
    }

    /**
     * @return array
     */
    private function getDataFromRatp()
    {
        try {
            $client = new Client();
            $res    = $client->request('GET', self::ENTRYPOINT_RATP, [
                'headers' => [
                    'User-Agent' => 'Dalvik/2.1.0 (Linux; U; Android 5.0; Google Nexus 4 - 5.0.0 - API 21 - 768x1280 Build/LRX21M)'
                ]
            ]);

            $data = json_decode($res->getBody()->getContents(), JSON_OBJECT_AS_ARRAY);

            return $this->formatRatpData($data);
        } catch (\Exception $exception) {
            return [
                'message' => 'Something went wrong'
            ];
        }
    }


    /**
     * @return array
     */
    private function getDataFromIxxi()
    {
        try {
            $client = new Client();
            $res    = $client->request('GET', $this->getEntryPointIxxi(), [
                'headers' => [
                    'User-Agent' => 'Dalvik/2.1.0 (Linux; U; Android 5.0; Google Nexus 4 - 5.0.0 - API 21 - 768x1280 Build/LRX21M)'
                ]
            ]);

            $data = json_decode($res->getBody()->getContents(), JSON_OBJECT_AS_ARRAY);

            return $this->formatIxxiData($data);
        } catch (\Exception $exception) {
            return [
                'message' => 'Something went wrong'
            ];
        }
    }

    /**
     * @param array $data
     * @return array
     */
    private function formatIxxiData($data = [])
    {
        $results = [];

        foreach ($data['events'] as $event) {
            foreach ($event['incidents'] as $incident) {
                foreach ($incident['lines'] as $line) {
                    if ($event['startDate'] <= date('c') && $event['endDate'] >= date('c')) {
                        $results[NamesHelper::getSlug($line['groupOfLinesName'])][$line['name']][$event['startDate']] = [
                            'message'          => $line['message'],
                            'shortMessage'     => $line['shortMessage'],
                            'incidentSeverity' => $line['incidentSeverity'],
                            'typeName'         => $event['typeName'],
                            'startDate'        => $event['startDate'],
                            'endDate'          => $event['startDate']
                        ];
                    }
                }
            }
        }

        return $results;
    }

    /**
     * @param array $data
     * @return array
     */
    public function formatRatpData($data = [])
    {
        $results = [];

        $matching_ressources = [
            'metros'   => 'metro',
            'rers'     => 'rer',
            'tramways' => 'tram'
        ];

        foreach ($matching_ressources as $route => $ratp_route) {
            $data_name = $matching_ressources[$route];

            foreach ($data['status'][$data_name]['lines'] as $line => $value) {
                $line = strtolower(str_replace('T', '', $line));

                $results[$route][] = [
                    'line'    => strtoupper($line),
                    'slug'    => $value['name'],
                    'title'   => $value['title'],
                    'message' => $value['message']
                ];
            }
        }

        return $results;
    }

    /**
     * @return string
     */
    private function getEntryPointIxxi()
    {
        return self::ENTRYPOINT_IXXI . '&keyapp=' . $this->apiKey . '&tmp=' . time();
    }
}