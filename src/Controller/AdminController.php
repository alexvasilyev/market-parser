<?php

namespace App\Controller;


use App\Form\SimpleParserType;
use App\Service\Parser;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class AdminController
 * @package AppBundle\Controller
 *
 * @Route("/admin")
 * @Security("has_role('ROLE_ADMIN')")
 */
class AdminController extends Controller
{
    /**
     * @Route("/", name="admin_landing")
     * @Template()
     */
    public function landingAction()
    {
        return [];
    }

    /**
     * @Route("/simple-parser", name="admin_simple_parser")
     * @Template()
     */
    public function simpleParserAction(Request $request)
    {
        $form = $this->createForm(SimpleParserType::class);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            /** @var Parser $parser */
            $parser = $this->get(Parser::class);
            $searchOptions = ['name' => $data['q']];
            $prices = $parser->parseMarket($searchOptions);

            return [
                'form' => $form->createView(),
                'prices' => $prices,
            ];
        }

        return [
            'form' => $form->createView(),
            'prices' => [],
        ];
    }
}
