<?php

namespace App\Command;

use App\Service\Parser;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ParserCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('app:parser:parse')
            ->setDescription('Parse motr market')
            ->setHelp('Parser');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln([
            'Start parsing'
        ]);

        $searchOptions = [
            'name' => 'knife',
            'minAtk' => 0,
            'minMatk' => 0,
            'weaponLv' => 0,
            'charLv' => 175,
            'minSlots' => 3,
            'maxSlots' => 4,
        ];
        $enchantRequisites = [
            'ASPD +2',
            'DEX',
        ];

        /** @var Parser $parser */
        $parser = $this->getContainer()->get(Parser::class);
        $prices = $parser->parseMarket($searchOptions);

        $filteredPrices = $this->filterKnifeEnchants($prices, $enchantRequisites);

        foreach ($filteredPrices as $price) {
            $output->writeln($price['item_beauty']);
            $output->writeln("{$price['price']}, {$price['place']} - {$price['vendor']}({$price['vend_name']}), {$price['quantity']}");
            $output->writeln('');
        }
        $output->writeln('Finished parsing');
    }

    /**
     * @param $requisite
     * @param $price
     *
     * @return false|int
     */
    protected function hasEnchant($requisite, $price)
    {
        $requisite = preg_quote($requisite);
        $pattern = "/<img style=\"[^\"]+\" src=\"[^\"]+\" title=\"{$requisite}/i";

        return preg_match($pattern, $price['item']);
    }

    protected function beautifyPriceItem($price)
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
    protected function filterKnifeEnchants($prices, $enchantRequisites): array
    {
        $filteredPrices = [];
        foreach ($prices as $price) {
            $hasAllRequisites = true;
            foreach ($enchantRequisites as $requisite) {
                $hasAllRequisites = $hasAllRequisites && $this->hasEnchant($requisite, $price);
            }
            if ($hasAllRequisites) {
                $filteredPrices[] = $this->beautifyPriceItem($price);
            }
        }

        return $filteredPrices;
    }
}
