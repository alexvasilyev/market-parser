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

        /** @var Parser $parser */
        $parser = $this->getContainer()->get(Parser::class);

        $prices = $parser->parseKnives();

        $iA = 0;
        $iAD = 0;

        $aspdPrices = [];
        $aspdDexPrices = [];
        foreach ($prices as $price) {
            if (false !== strpos($price['item'], 'Knife [3]')) {
                $hasEnchantASPD = $this->hasEnchant('ASPD +2', $price);
                if ($hasEnchantASPD) {
                    $iA++;
                    $aspdPrices[] = $this->beautifyPriceItem($price);
                }
                if ($hasEnchantASPD && $this->hasEnchant('DEX', $price)) {
                    $iAD++;
                    $aspdDexPrices[] = $this->beautifyPriceItem($price);
                }
            }

        }
        $output->writeln("{$iA} knives with ASPD found");
        $output->writeln("{$iAD} knives with ASPD and DEX found");

        foreach ($aspdDexPrices as $price) {
            $output->writeln($price['item_beauty']);
            $output->writeln("{$price['price']}, {$price['place']} - {$price['vendor']}({$price['vend_name']}), {$price['quantity']}");
            $output->writeln('');
        }
        $output->writeln('Finished parsing');
    }

    /**
     * @param $enchant
     * @param $price
     *
     * @return false|int
     */
    protected function hasEnchant($enchant, $price)
    {
        $enchant = preg_quote($enchant);
        $pattern = "/<img style=\"[^\"]+\" src=\"[^\"]+\" title=\"{$enchant}/i";

        return preg_match($pattern, $price['item']);
    }

    protected function beautifyPriceItem($price)
    {
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
        $price['item_beauty'] = trim(preg_replace('/\s\s+/', ' ', $re['item`    ']));

        return $price;
    }
}
