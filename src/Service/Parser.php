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
     * @param string $searchTerm
     *
     * @return array
     */
    public function parseMarket($searchTerm = ''): array
    {
        $crawler = $this->getClient()->request('GET', 'http://motr-online.com/members/vendingstat');
        $form = $crawler->selectButton('Обновить')->form();
        $form['name'] = $searchTerm;
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
        $rowspanContent[1] = null;
        /**
         * @var int $i
         * @var \DOMElement $tr
         */
        foreach ($tableRows as $i => $tr) {
            if (0 == $i) {
                continue;
            }

            $tdRes = [
                'place' => '',
                'item' => '',
            ];
            /**
             * @var int $j
             * @var \DOMElement $td
             */
            $rowspan[1]--;
            foreach ($tr->childNodes as $j => $td) {
                $colspan = 1;
                $item = '';
                switch ($j) {
                    case 0:
                        $tdRes['place'] = $td->nodeValue;
                        break;
                    case 1:
                        if ($rowspan[1] <= 1) {
                            $item = $td->ownerDocument->saveXML($td);
                            $tdRes['item'] = $item;
                            $rowspan[1] = $td->getAttribute('rowspan');
                            if ($rowspan[1] > 1) {
                                $rowspanContent[1] = $item;
                            } else {
                                $rowspanContent[1] = null;
                            }
                        }

                        break;
                    case 2:
                        $colspan = ("" !== $td->getAttribute('colspan')) ? $td->getAttribute('colspan') : 1;
                        $tdRes['vendor'] = $td->nodeValue;

                        break;
                    case 3:
                        if (1 == $colspan) {
                            $tdRes['vend_name'] = $td->nodeValue;
                        }

                        break;
                    case 4:
                        $tdRes['quantity'] = $td->nodeValue;

                        break;
                    case 5:
                        $tdRes['price'] = $td->nodeValue;

                        break;
                }
                if (!is_null($rowspanContent[1])) {
                    $tdRes['item'] = $rowspanContent[1];
                }
            }
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

}
