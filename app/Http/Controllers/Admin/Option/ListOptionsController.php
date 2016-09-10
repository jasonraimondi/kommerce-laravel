<?php
namespace App\Http\Controllers\Admin\Option;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use inklabs\kommerce\Action\Option\ListOptionsQuery;
use inklabs\kommerce\Action\Option\Query\ListOptionsRequest;
use inklabs\kommerce\Action\Option\Query\ListOptionsResponse;

class ListOptionsController extends Controller
{
    public function index(Request $httpRequest)
    {
        $request = new ListOptionsRequest(
            $httpRequest->query('q'),
            $this->getPaginationDTO(20)
        );

        $response = new ListOptionsResponse();
        $this->dispatchQuery(new ListOptionsQuery($request, $response));

        $options = $response->getOptionDTOs();
        $pagination = $response->getPaginationDTO();

        return $this->renderTemplate(
            'admin/option/index.twig',
            [
                'options' => $options,
                'pagination' => $pagination,
            ]
        );
    }
}