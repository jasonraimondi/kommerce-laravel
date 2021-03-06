<?php
namespace App\Http\Controllers\Admin\Product;

use App\Http\Controllers\Controller;

class ListProductImagesController extends Controller
{
    public function index($productId)
    {
        $product = $this->getProductWithAllData($productId);

        return $this->renderTemplate(
            '@admin/product/images.twig',
            [
                'product' => $product,
            ]
        );
    }
}
