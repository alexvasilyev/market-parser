<?php

namespace App\Service;


use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;

class Parser
{
    /**
     * @var Client
     */
    private $client;
    private $accountName;
    private $accountPass;

    public function __construct($accountName, $accountPass)
    {
        $this->client = new Client();
        $this->accountName = $accountName;
        $this->accountPass = $accountPass;
    }

    public function getClient()
    {
        return $this->client;
    }
    
    /**
     * @return Crawler
     */
    public function authenticate(): Crawler
    {
        $crawler = $this->client->request('GET', 'http://motr-online.com/login');
        $form = $crawler->selectButton('Login')->form();
        $form['login'] = $this->accountName;
        $form['pass'] = $this->accountPass;

        // submits the login form
        $crawler = $this->client->submit($form);

        return $crawler;
    }

    /**
     * @return array
     */
    public function parseKnives()
    {
        $this->authenticate();

        return $this->parseMarket('knife');
    }

    /**
     * @param array $searchOptions
     *
     * @return array
     */
    public function parseMarket($searchOptions): array
    {
        $defaultOptions = [
            'name' => '',
            'minAtk' => 0,
            'minMatk' => 0,
            'weaponLv' => 0,
            'charLv' => 175,
            'minSlots' => 0,
            'maxSlots' => 4,
        ];
        $searchOptions = array_merge($defaultOptions, $searchOptions);

        $this->authenticate();

        $crawler = $this->getClient()->request('GET', 'http://motr-online.com/members/vendingstat');
        $form = $crawler->selectButton('Обновить')->form();
        foreach ($searchOptions as $option => $value) {
            $form[$option] = $value;
        }
        $crawler = $this->getClient()->submit($form);

        return $this->parsePageResults($crawler);
    }

    /**
     * @param $crawler
     *
     * @return array
     */
    private function parsePageResults($crawler)
    {
        /** @var \DOMNodeList $tableRows */
        $tableRows = $crawler->filterXPath('//table[contains(@class, \'tableBord\')]/tr');

        $prices = [];
        $rowspan[1] = 1;
        $rowspan[2] = 1;
        $rowspan[4] = 1;
        $rowspan[5] = 1;
        $rowspanContent['item'] = null;
        $rowspanContent['vendor'] = null;
        $colspan = 1;
        /**
         * @var int $i
         * @var \DOMElement $tr
         */
        foreach ($tableRows as $i => $tr) {
            if (0 == $i) {
                continue;
            }

            /**
             * @var int $j
             * @var \DOMElement $td
             */
            $rowspan[1]--;
            $rowspan[2]--;
            $rowspan[4]--;
            $rowspan[5]--;
            $selfColspan = false;
            foreach ($tr->childNodes as $j => $td) {
                switch ($j) {
                    case 0:
                        $tdRes['place'] = $td->nodeValue;

                        break;
                    case 1:
                        /** got contents */
                        if (0 == $rowspan[1]) {
                            $colspan = 1;
                            $tdRes['item'] = $td->ownerDocument->saveXML($td);
                            $rowspan[1] = ("" != $td->getAttribute('rowspan')) ? (int) $td->getAttribute('rowspan') : 1;
                            if (1 == $rowspan[1]) {
                                unset($rowspanContent['item']);
                            } else {
                                $rowspanContent['item'] = $tdRes['item'];
                            }
                        } else {
                            /** take info from cache */
                            $tdRes['item'] = $rowspanContent['item'];

                            if (0 == $rowspan[2]) {
                                $tdRes['vendor'] = $td->nodeValue;
                                $rowspan[2] = ("" != $td->getAttribute('rowspan')) ? (int) $td->getAttribute('rowspan') : 1;

                                if (1 == $rowspan[2]) {
                                    unset($rowspanContent['vendor']);
                                } else {
                                    $rowspanContent['vendor'] = $tdRes['vendor'];
                                }
                            }
                            /** get vendor */
                            /** Check for colspan */
                            $colspan = ("" != $td->getAttribute('colspan')) ? (int) $td->getAttribute('colspan') : 1;
                        }

                        break;
                    case 2:
                        if (0 == $rowspan[2]) {
                            $tdRes['vendor'] = $td->nodeValue;
                            $rowspan[2] = ("" != $td->getAttribute('rowspan')) ? (int) $td->getAttribute('rowspan') : 1;

                            if (1 == $rowspan[2]) {
                                unset($rowspanContent['vendor']);
                            } else {
                                $rowspanContent['vendor'] = $tdRes['vendor'];
                            }
                        }
                        /** get vendor */
                        /** Check for colspan */
                        $colspan = ("" != $td->getAttribute('colspan')) ? (int) $td->getAttribute('colspan') : 1;
                        if (1 != $colspan) {
                            $selfColspan = true;
                            $tdRes['vend_name'] = "NPC vendor";
                        }

                        break;
                    case 3:
                        if (1 == $colspan) {
                            $tdRes['vend_name'] = $td->nodeValue;
                        } else if ($selfColspan) {
                            if (0 == $rowspan[4]) {
                                $tdRes['quantity'] = $td->nodeValue;
                                $rowspan[4] = ("" != $td->getAttribute('rowspan')) ? (int) $td->getAttribute('rowspan') : 1;

                                if (1 == $rowspan[4]) {
                                    unset($rowspanContent['quantity']);
                                } else {
                                    $rowspanContent['quantity'] = $tdRes['quantity'];
                                }
                            }
                        }

                        break;
                    case 4:
                        if (1 == $colspan) {
                            if (0 == $rowspan[4]) {
                                $tdRes['quantity'] = $td->nodeValue;
                                $rowspan[4] = ("" != $td->getAttribute('rowspan')) ? (int) $td->getAttribute('rowspan') : 1;

                                if (1 == $rowspan[4]) {
                                    unset($rowspanContent['quantity']);
                                } else {
                                    $rowspanContent['quantity'] = $tdRes['quantity'];
                                }
                            }
                        } else if ($selfColspan) {
                            if (0 == $rowspan[5]) {
                                $tdRes['price'] = $td->nodeValue;
                                $rowspan[5] = ("" != $td->getAttribute('rowspan')) ? (int) $td->getAttribute('rowspan') : 1;

                                if (1 == $rowspan[5]) {
                                    unset($rowspanContent['price']);
                                } else {
                                    $rowspanContent['price'] = $tdRes['price'];
                                }
                            }
                        }

                        break;
                    case 5:
                        if (0 == $rowspan[5]) {
                            $tdRes['price'] = $td->nodeValue;
                            $rowspan[5] = ("" != $td->getAttribute('rowspan')) ? (int) $td->getAttribute('rowspan') : 1;

                            if (1 == $rowspan[5]) {
                                unset($rowspanContent['price']);
                            } else {
                                $rowspanContent['price'] = $tdRes['price'];
                            }
                        }

                        break;
                }
            }
            if (1 < $rowspan[1] && '' == $tdRes['item']) {
                $tdRes['item'] = $rowspanContent['item'];
            }
            if (1 < $rowspan[2] && '' == $tdRes['vendor']) {
                $tdRes['vendor'] = $rowspanContent['vendor'];
            }
            if (1 != $colspan) {
                $tdRes['vend_name'] = "NPC vendor";
            }
            if (1 < $rowspan[4] && '' == $tdRes['quantity']) {
                $tdRes['quantity'] = $rowspanContent['quantity'];
            }
            if (1 < $rowspan[5] && '' == $tdRes['price']) {
                $tdRes['price'] = $rowspanContent['price'];
            }

            $tdRes['item'] = $this->cleanItem($tdRes['item']);
            $prices[] = $tdRes;
        }

        $nextPage = $crawler->selectButton('След.стр');
        if ($nextPage->count()) {
            $form = $nextPage->form();
            $crawler = $this->getClient()->submit($form);
            $prices = array_merge($prices, $this->parsePageResults($crawler));
        }

        return $prices;
    }

    /**
     * @param $item
     *
     * @return mixed
     */
    protected function cleanItem($item)
    {
        $patterns = [
            '/<td[^>]+>/i',
            '/<\/td>/i'
        ];

        return $item = preg_replace($patterns, '', $item);
    }
}
