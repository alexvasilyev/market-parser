<?php

namespace App\Service;


class ResultsFilter
{
    /**
     * @param $requisite
     * @param $price
     *
     * @return false|int
     */
    public function hasRequisite($requisite, $price)
    {
        $requisite = preg_quote($requisite);
        $pattern = "/<img style=\"[^\"]+\" src=\"[^\"]+\" title=\"{$requisite}/i";

        return preg_match($pattern, $price['item']);
    }

    /**
     * @param array $price
     *
     * @return array
     */
    protected function beautifyPriceItem($price): array
    {
        /**
         * Remove td from $price['item']
         */
        $re = preg_replace('/<\/?td[^>]*>/i', '', $price);
        $price['item'] = trim(preg_replace('/\s\s+/', ' ', $re['item']));
        $patterns = [
            '/<img style="[^"]+" src="[^"]+" title="([^"]+)"[^>]+>/i',
            '/<img style="[^"]+" src="[^"]+" alt="[^"]+"[^>]+>/i',
            '/<\/?td[^>]*>/i',
            '/<\/?a[^>]*>/i',
        ];
        $replace = [
            '{$1}',
            '',
            '',
            '',
        ];

        $re = preg_replace($patterns, $replace, $price);
        $price['item_beauty'] = trim(preg_replace('/\s\s+/', ' ', $re['item']));

        return $price;
    }

    /**
     * @param $prices
     * @param $enchantRequisites
     *
     * @return array
     */
    public function filter($prices, $enchantRequisites): array
    {
        $filteredPrices = [];
        foreach ($prices as $price) {
            $hasAllRequisites = true;
            foreach ($enchantRequisites as $requisite) {
                $hasAllRequisites = $hasAllRequisites && $this->hasRequisite($requisite, $price);
            }
            if ($hasAllRequisites) {
                $filteredPrices[] = $this->beautifyPriceItem($price);
            }
        }

        return $filteredPrices;
    }
}
