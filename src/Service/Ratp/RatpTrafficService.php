<?php

declare(strict_types=1);

namespace App\Service\Ratp;

use App\Client\IxxiApiClient;
use App\Client\RatpWebsiteClient;
use App\Utils\NameHelper;

class RatpTrafficService extends AbstractRatpService implements RatpServiceInterface
{
    /**
     * @var IxxiApiClient
     */
    private $ixxiApiClient;

    /**
     * @var RatpWebsiteClient
     */
    private $ratpWebsiteClient;

    public function __construct()
    {
        $this->ixxiApiClient     = new IxxiApiClient();
        $this->ratpWebsiteClient = new RatpWebsiteClient();
    }

    /**
     * @return array
     */
    protected function getTraffic(): array
    {
        $ixxiData = $this->ixxiApiClient->getData();
        $ratpData = $this->ratpWebsiteClient->getData();

        $completeData = $this->mergeDataSources($ratpData, $ixxiData);

        return $completeData;
    }

    /**
     * @param array $ratpData
     * @param array $ixxiData
     *
     * @return array
     */
    private function mergeDataSources(array $ratpData, array $ixxiData): array
    {
        // merge only RER C, D and E
        $allowedRers = [
            'c',
            'd',
            'e'
        ];

        foreach ($allowedRers as $allowedRer) {
            if (isset($ixxiData['rers'][$allowedRer])) {
                $rer = $ixxiData['rers'][$allowedRer];
                ksort($rer);

                $firstEvent = current($rer);

                $information = [
                    'line'    => strtoupper($allowedRer),
                    'slug'    => NameHelper::statusSlug($firstEvent['typeName']),
                    'title'   => $firstEvent['typeName'],
                    'message' => $firstEvent['message']
                ];
            } else {
                $information = [
                    'line'    => strtoupper($allowedRer),
                    'slug'    => 'normal',
                    'title'   => 'Trafic normal',
                    'message' => 'Trafic normal sur l\'ensemble de la ligne.'
                ];
            }
            $tmpRers[$allowedRer] = $information;
        }

        ksort($tmpRers);

        foreach ($tmpRers as $rer) {
            $ratpData['rers'][] = $rer;
        }

        return $ratpData;
    }
}
