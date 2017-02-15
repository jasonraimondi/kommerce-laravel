<?php
namespace App\Http\Controllers;

use App\Lib\Arr;
use App\Lib\CSRFTokenGenerator;
use App\Lib\KommerceConfiguration;
use App\Lib\LaravelRouteUrl;
use DateTime;
use DateTimeZone;
use Gregwar\Captcha\CaptchaBuilder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesResources;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use inklabs\kommerce\Action\Attribute\GetAttributeQuery;
use inklabs\kommerce\Action\Attribute\GetAttributeValueQuery;
use inklabs\kommerce\Action\Attribute\Query\GetAttributeRequest;
use inklabs\kommerce\Action\Attribute\Query\GetAttributeResponse;
use inklabs\kommerce\Action\Attribute\Query\GetAttributeValueRequest;
use inklabs\kommerce\Action\Attribute\Query\GetAttributeValueResponse;
use inklabs\kommerce\Action\Cart\CopyCartItemsCommand;
use inklabs\kommerce\Action\Cart\CreateCartCommand;
use inklabs\kommerce\Action\Cart\GetCartBySessionIdQuery;
use inklabs\kommerce\Action\Cart\GetCartByUserIdQuery;
use inklabs\kommerce\Action\Cart\GetCartQuery;
use inklabs\kommerce\Action\Cart\Query\GetCartBySessionIdRequest;
use inklabs\kommerce\Action\Cart\Query\GetCartBySessionIdResponse;
use inklabs\kommerce\Action\Cart\Query\GetCartByUserIdRequest;
use inklabs\kommerce\Action\Cart\Query\GetCartByUserIdResponse;
use inklabs\kommerce\Action\Cart\Query\GetCartRequest;
use inklabs\kommerce\Action\Cart\Query\GetCartResponse;
use inklabs\kommerce\Action\Cart\RemoveCartCommand;
use inklabs\kommerce\Action\Cart\SetCartSessionIdCommand;
use inklabs\kommerce\Action\Cart\SetCartUserCommand;
use inklabs\kommerce\Action\CartPriceRule\GetCartPriceRuleQuery;
use inklabs\kommerce\Action\CartPriceRule\Query\GetCartPriceRuleRequest;
use inklabs\kommerce\Action\CartPriceRule\Query\GetCartPriceRuleResponse;
use inklabs\kommerce\Action\CatalogPromotion\GetCatalogPromotionQuery;
use inklabs\kommerce\Action\CatalogPromotion\Query\GetCatalogPromotionRequest;
use inklabs\kommerce\Action\CatalogPromotion\Query\GetCatalogPromotionResponse;
use inklabs\kommerce\Action\Configuration\GetConfigurationsByKeysQuery;
use inklabs\kommerce\Action\Configuration\Query\GetConfigurationsByKeysRequest;
use inklabs\kommerce\Action\Configuration\Query\GetConfigurationsByKeysResponse;
use inklabs\kommerce\Action\Coupon\GetCouponQuery;
use inklabs\kommerce\Action\Coupon\Query\GetCouponRequest;
use inklabs\kommerce\Action\Coupon\Query\GetCouponResponse;
use inklabs\kommerce\Action\Option\GetOptionQuery;
use inklabs\kommerce\Action\Option\Query\GetOptionRequest;
use inklabs\kommerce\Action\Option\Query\GetOptionResponse;
use inklabs\kommerce\Action\Order\GetOrderItemQuery;
use inklabs\kommerce\Action\Order\GetOrderQuery;
use inklabs\kommerce\Action\Order\Query\GetOrderItemRequest;
use inklabs\kommerce\Action\Order\Query\GetOrderItemResponse;
use inklabs\kommerce\Action\Order\Query\GetOrderRequest;
use inklabs\kommerce\Action\Order\Query\GetOrderResponse;
use inklabs\kommerce\Action\Product\GetProductQuery;
use inklabs\kommerce\Action\Product\GetRandomProductsQuery;
use inklabs\kommerce\Action\Product\Query\GetProductRequest;
use inklabs\kommerce\Action\Product\Query\GetProductResponse;
use inklabs\kommerce\Action\Product\Query\GetRandomProductsRequest;
use inklabs\kommerce\Action\Product\Query\GetRandomProductsResponse;
use inklabs\kommerce\Action\Shipment\GetShipmentTrackerQuery;
use inklabs\kommerce\Action\Shipment\Query\GetShipmentTrackerRequest;
use inklabs\kommerce\Action\Shipment\Query\GetShipmentTrackerResponse;
use inklabs\kommerce\Action\Tag\GetTagQuery;
use inklabs\kommerce\Action\Tag\Query\GetTagRequest;
use inklabs\kommerce\Action\Tag\Query\GetTagResponse;
use inklabs\kommerce\EntityDTO\AttributeDTO;
use inklabs\kommerce\EntityDTO\AttributeValueDTO;
use inklabs\kommerce\EntityDTO\CartDTO;
use inklabs\kommerce\EntityDTO\CartPriceRuleDTO;
use inklabs\kommerce\EntityDTO\CatalogPromotionDTO;
use inklabs\kommerce\EntityDTO\CouponDTO;
use inklabs\kommerce\EntityDTO\OptionDTO;
use inklabs\kommerce\EntityDTO\OrderDTO;
use inklabs\kommerce\EntityDTO\OrderItemDTO;
use inklabs\kommerce\EntityDTO\PaginationDTO;
use inklabs\kommerce\EntityDTO\ProductDTO;
use inklabs\kommerce\EntityDTO\ShipmentTrackerDTO;
use inklabs\kommerce\EntityDTO\TagDTO;
use inklabs\kommerce\EntityDTO\UserDTO;
use inklabs\kommerce\Exception\EntityNotFoundException;
use inklabs\kommerce\Exception\InvalidArgumentException;
use inklabs\kommerce\Exception\KommerceException;
use inklabs\kommerce\Lib\Command\CommandInterface;
use inklabs\kommerce\Lib\Query\QueryInterface;
use inklabs\kommerce\Lib\UuidInterface;
use inklabs\KommerceTemplates\Lib\AssetLocationService;
use inklabs\KommerceTemplates\Lib\TwigTemplate;
use inklabs\KommerceTemplates\Lib\TwigThemeConfig;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintViolationListInterface;

class Controller extends BaseController
{
    use AuthorizesRequests, AuthorizesResources, DispatchesJobs, ValidatesRequests;

    /** @var CartDTO */
    private $cartDTO;

    /** @var UserDTO */
    private $userDTO;

    /** @var KommerceConfiguration */
    private $kommerceConfiguration;

    /** @var KommerceConfiguration */
    private $adminKommerceConfiguration;

    public function __construct()
    {
        $sessionId = $this->getSessionId();
        $isAdmin = false;
        $userId = null;
        $user = $this->getUserFromSession();
        if ($user !== null) {
            $userId = $user->id;
            $isAdmin = $this->userHasAdminRole($user);
            $twig = $this->getTwigTemplate();
            $twig->addGlobal('user', $user);
        }

        $this->kommerceConfiguration = new KommerceConfiguration(
            $userId,
            $sessionId,
            $isAdmin
        );

        $this->adminKommerceConfiguration = new KommerceConfiguration(
            null,
            null,
            true
        );
    }

    public function userHasAdminRole(UserDTO $user)
    {
        foreach ($user->userRoles as $userRole) {
            if ($userRole->userRoleType->isAdmin) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return CaptchaBuilder
     */
    public function getCaptchaBuilder()
    {
        $builder = new CaptchaBuilder();
        $builder->setBackgroundColor(255, 255, 255);
        $builder->setIgnoreAllEffects(true);
        $builder->build();

        $this->setCaptchaPhraseToSession($builder->getPhrase());

        return $builder;
    }

    /**
     * @todo Fix: This is an awful way to set session variables
     * @param string $phrase
     */
    private function setCaptchaPhraseToSession($phrase)
    {
        /** @var \Illuminate\Session\Store $session */
        $session = app('session');
        $session->set('captchaPhrase', $phrase);
    }

    /**
     * @todo Fix: This is an awful way to get session variables
     * @return string
     */
    protected function getCaptchaPhraseFromSession()
    {
        /** @var \Illuminate\Session\Store $session */
        $session = app('session');
        $phrase = $session->get('captchaPhrase');
        $session->forget('captchaPhrase');
        return $phrase;
    }

    protected function saveUserToSession(UserDTO $user)
    {
        /** @var \Illuminate\Session\Store $session */
        $session = app('session');
        $session->set('user', $user);
        $this->kommerceConfiguration->setUserId($user->id);
    }

    protected function removeUserFromSession()
    {
        /** @var \Illuminate\Session\Store $session */
        $session = app('session');

        if (! $session->has('user')) {
            abort(401);
        }

        $session->remove('user');
    }

    /**
     * @return UserDTO
     */
    protected function getUserFromSessionOrAbort()
    {
        $user = $this->getUserFromSession();

        if ($user === null) {
            abort(401);
        }

        return $user;
    }

    /**
     * @return null|UserDTO
     */
    protected function getUserFromSession()
    {
        /** @var \Illuminate\Session\Store $session */
        $session = app('session');

        if (! $session->has('user')) {
            return null;
        }

        return $session->get('user');
    }

    protected function getPricing()
    {
        return $this->kommerceConfiguration->getPricing();
    }

    protected function getCartCalculator()
    {
        return $this->kommerceConfiguration->getCartCalculator();
    }

    public function getDTOBuilderFactory()
    {
        return $this->kommerceConfiguration->getDTOBuilderFactory();
    }

    protected function getStoreAddress()
    {
        return $this->kommerceConfiguration->getStoreAddress();
    }

    protected function dispatch(CommandInterface $command)
    {
        $this->kommerceConfiguration->dispatch($command);
    }

    protected function dispatchQuery(QueryInterface $query)
    {
        $this->kommerceConfiguration->dispatchQuery($query);
    }

    protected function adminDispatch(CommandInterface $command)
    {
        $this->adminKommerceConfiguration->dispatch($command);
    }

    protected function adminDispatchQuery(QueryInterface $query)
    {
        $this->adminKommerceConfiguration->dispatchQuery($query);
    }

    /**
     * @return string
     */
    protected function getRemoteIP4()
    {
        return request()->ip();
    }

    /**
     * @return string
     */
    protected function getUserAgent()
    {
        return request()->header('User-Agent');
    }

    /**
     * @return string
     */
    private function getSessionId()
    {
        return session()->getId();
    }

    /**
     * @return \Illuminate\Session\Store
     */
    private function getSession()
    {
        return session();
    }

    /**
     * @return CartDTO
     */
    protected function getCart()
    {
        $cartId = $this->getCartId();

        $request = new GetCartRequest($cartId);
        $response = new GetCartResponse($this->getCartCalculator());
        $this->dispatchQuery(new GetCartQuery($request, $response));

        return $response->getCartDTOWithAllData();
    }

    /**
     * @return string
     */
    protected function getCartId()
    {
        if ($this->cartDTO != null) {
            return $this->cartDTO->id->getHex();
        }

        try {
            $cartDTO = $this->getCartFromSession();
            return $cartDTO->id->getHex();
        } catch (EntityNotFoundException $e) {
        }

        $this->createNewCart();

        return $this->cartDTO->id->getHex();
    }

    /**
     * @return CartDTO
     * @throws EntityNotFoundException
     */
    private function getCartFromSession()
    {
        $request = new GetCartBySessionIdRequest($this->getSessionId());
        $response = new GetCartBySessionIdResponse($this->getCartCalculator());
        $this->adminDispatchQuery(new GetCartBySessionIdQuery($request, $response));

        return $response->getCartDTO();
    }

    /**
     * @param string $userId
     * @return CartDTO
     */
    private function getCartByUserId($userId)
    {
        $request = new GetCartByUserIdRequest($userId);
        $response = new GetCartByUserIdResponse($this->getCartCalculator());
        $this->adminDispatchQuery(new GetCartByUserIdQuery($request, $response));

        return $response->getCartDTO();
    }

    protected function createNewCart()
    {
        $userId = null;
        if ($this->userDTO !== null) {
            $userId = $this->userDTO->id->getHex();
        }

        $createCartCommand = new CreateCartCommand(
            $this->getRemoteIP4(),
            $userId,
            $this->getSessionId()
        );
        $this->dispatch($createCartCommand);
        $cartId = $createCartCommand->getCartId();

        $request = new GetCartRequest($cartId);
        $response = new GetCartResponse($this->getCartCalculator());
        $this->dispatchQuery(new GetCartQuery($request, $response));

        $this->cartDTO = $response->getCartDTO();
    }

    /**
     * @param $name
     * @param array $context
     * @return \Illuminate\Contracts\Routing\ResponseFactory | \Symfony\Component\HttpFoundation\Response
     */
    protected function renderTemplate($name, $context = [])
    {
        $twig = $this->getTwigTemplate();

        $this->setGlobalFlashVariables($twig);

        return response(
            $twig->render($name, $context)
        );
    }

    private function setGlobalFlashVariables(TwigTemplate $twig)
    {
        $session = $this->getSession();
        if ($session->isStarted()) {
            $flashValues = [
                'flashMessages',
                'templateFlashMessages',
                'flashFormErrors',
            ];

            foreach ($flashValues as $flashValue) {
                if ($session->has($flashValue)) {
                    $twig->addGlobal($flashValue, $session->get($flashValue));
                    $session->forget($flashValue);
                }
            }
        }
    }

    /**
     * @return TwigTemplate
     */
    protected function getTwigTemplate()
    {
        static $twigTemplate = null;
        if ($twigTemplate === null) {
            $twigTemplate = new TwigTemplate(
                TwigThemeConfig::loadConfigFromTheme(env('STORE_THEME'), 'store'),
                TwigThemeConfig::loadConfigFromTheme(env('ADMIN_THEME'), 'admin'),
                new CSRFTokenGenerator(),
                new LaravelRouteUrl(),
                env('STORE_TIMEZONE'),
                env('STORE_DATE_FORMAT'),
                env('STORE_TIME_FORMAT'),
                env('TWIG_PROFILER_ENABLED'),
                env('TWIG_DEBUG_ENABLED')
            );
        }

        return $twigTemplate;
    }

    /**
     * @param string $message
     */
    protected function flashSuccess($message = '')
    {
        $this->flashMessage('success', $message);
    }

    /**
     * @param string $flashTemplate
     * @param array $data
     * @throws InvalidArgumentException
     */
    protected function flashTemplateError($flashTemplate, array $data = [])
    {
//        if (strpos($flashTemplate, '@store/flash/') === false) {
//            throw new InvalidArgumentException('Can only flash from @store/flash/');
//        }
        $this->flashTemplateMessage('error', $flashTemplate, $data);
    }

    /**
     * @param string $flashTemplate
     * @param array $data
     * @throws InvalidArgumentException
     */
    protected function flashTemplateSuccess($flashTemplate, array $data = [])
    {
        if (strpos($flashTemplate, '@store/flash/') === false) {
            throw new InvalidArgumentException('Can only flash from @store/flash/');
        }
        $this->flashTemplateMessage('success', $flashTemplate, $data);
    }

    /**
     * @param string $message
     */
    protected function flashError($message = '')
    {
        $this->flashMessage('danger', $message);
    }

    /**
     * @param string $message
     */
    protected function flashWarning($message = '')
    {
        $this->flashMessage('warning', $message);
    }

    public function flashGenericWarning($message = 'Something went wrong.')
    {
        $extraMessage = 'Please contact us at store@example.com';
        $this->flashWarning($message . ' ' . $extraMessage);
    }

    /**
     * @param string $type
     * @param string $message
     */
    private function flashMessage($type, $message = '')
    {
        $messages = session()->get('flashMessages', []);
        $messages[$type][] = $message;
        session()->flash('flashMessages', $messages);
    }

    protected function flashFormErrors(ConstraintViolationListInterface $newFormErrors)
    {
        /** @var ConstraintViolationListInterface | ConstraintViolationInterface[] $formErrors */
        $formErrors = session()->get('flashFormErrors', new ConstraintViolationList());
        $formErrors->addAll($newFormErrors);
        session()->flash('flashFormErrors', $formErrors);
    }

    /**
     * @param string $type
     * @param string $flashTemplate
     * @param array $data
     */
    private function flashTemplateMessage($type, $flashTemplate, array $data)
    {
        $messages = session()->get('templateFlashMessages', []);
        $messages[$type][] = [
            'flashTemplate' => $flashTemplate,
            'data' => $data,
        ];
        session()->flash('templateFlashMessages', $messages);
    }

    /**
     * @param int $maxResults
     * @return PaginationDTO
     */
    protected function getPaginationDTO($maxResults)
    {
        $page = request()->query('page');
        if (empty($page)) {
            $page = 1;
        }
        $paginationDTO = new PaginationDTO;
        $paginationDTO->maxResults = $maxResults;
        $paginationDTO->page = $page;
        $paginationDTO->isTotalIncluded = true;

        return $paginationDTO;
    }

    protected function getAssetLocationService()
    {
        $assetLocationService = new AssetLocationService();
        return $assetLocationService;
    }

    protected function getRandomProducts($limit)
    {
        $request = new GetRandomProductsRequest($limit);
        $response = new GetRandomProductsResponse($this->getPricing());
        $this->dispatchQuery(new GetRandomProductsQuery($request, $response));

        return $response->getProductDTOs();
    }

    protected function getConfigurationsByKeys(array $keys)
    {
        $request = new GetConfigurationsByKeysRequest($keys);
        $response = new GetConfigurationsByKeysResponse();
        $this->dispatchQuery(new GetConfigurationsByKeysQuery($request, $response));

        return $response->getConfigurationDTOs();
    }

    /**
     * @param $optionId
     * @return OptionDTO
     * @throws NotFoundHttpException
     */
    protected function getOptionWithAllData($optionId)
    {
        return $this->getOptionById($optionId)
            ->getOptionDTOWithAllData();
    }

    /**
     * @param string $optionId
     * @return GetOptionResponse
     * @throws NotFoundHttpException
     */
    private function getOptionById($optionId)
    {
        try {
            $request = new GetOptionRequest($optionId);
            $response = new GetOptionResponse($this->getPricing());
            $this->dispatchQuery(new GetOptionQuery($request, $response));
        } catch (EntityNotFoundException $e) {
            return abort(404);
        }

        return $response;
    }

    /**
     * @param $orderId
     * @return OrderDTO
     * @throws NotFoundHttpException
     */
    protected function getOrderWithAllData($orderId)
    {
        return $this->getOrderById($orderId)
            ->getOrderDTOWithAllData();
    }

    /**
     * @param string $orderId
     * @return GetOrderResponse
     * @throws NotFoundHttpException
     */
    private function getOrderById($orderId)
    {
        try {
            $request = new GetOrderRequest($orderId);
            $response = new GetOrderResponse();
            $this->dispatchQuery(new GetOrderQuery($request, $response));
        } catch (EntityNotFoundException $e) {
            return abort(404);
        }

        return $response;
    }

    /**
     * @param $shipmentTrackerId
     * @return ShipmentTrackerDTO
     * @throws NotFoundHttpException
     */
    protected function getShipmentTracker($shipmentTrackerId)
    {
        return $this->getShipmentTrackerById($shipmentTrackerId)
            ->getShipmentTrackerDTO();
    }

    /**
     * @param string $shipmentTrackerId
     * @return GetShipmentTrackerResponse
     * @throws NotFoundHttpException
     */
    private function getShipmentTrackerById($shipmentTrackerId)
    {
        try {
            $request = new GetShipmentTrackerRequest($shipmentTrackerId);
            $response = new GetShipmentTrackerResponse();
            $this->dispatchQuery(new GetShipmentTrackerQuery($request, $response));
        } catch (EntityNotFoundException $e) {
            return abort(404);
        }

        return $response;
    }

    /**
     * @param $orderItemId
     * @return OrderItemDTO
     * @throws NotFoundHttpException
     */
    protected function getOrderItem($orderItemId)
    {
        return $this->getOrderItemById($orderItemId)
            ->getOrderItemDTO();
    }

    /**
     * @param $orderItemId
     * @return OrderItemDTO
     * @throws NotFoundHttpException
     */
    protected function getOrderItemWithAllData($orderItemId)
    {
        return $this->getOrderItemById($orderItemId)
            ->getOrderItemDTOWithAllData();
    }

    /**
     * @param string $orderItemId
     * @return GetOrderItemResponse
     * @throws NotFoundHttpException
     */
    private function getOrderItemById($orderItemId)
    {
        try {
            $request = new GetOrderItemRequest($orderItemId);
            $response = new GetOrderItemResponse();
            $this->dispatchQuery(new GetOrderItemQuery($request, $response));
        } catch (EntityNotFoundException $e) {
            return abort(404);
        }

        return $response;
    }

    /**
     * @param $productId
     * @return ProductDTO
     * @throws NotFoundHttpException
     */
    protected function getProduct($productId)
    {
        return $this->getProductById($productId)
            ->getProductDTO();
    }

    /**
     * @param $productId
     * @return ProductDTO
     * @throws NotFoundHttpException
     */
    protected function getProductWithAllData($productId)
    {
        return $this->getProductById($productId)
            ->getProductDTOWithAllData();
    }

    /**
     * @param string $productId
     * @return GetProductResponse
     * @throws NotFoundHttpException
     */
    private function getProductById($productId)
    {
        try {
            $request = new GetProductRequest($productId);
            $response = new GetProductResponse($this->getPricing());
            $this->dispatchQuery(new GetProductQuery($request, $response));
        } catch (EntityNotFoundException $e) {
            return abort(404);
        }

        return $response;
    }

    /**
     * @param $cartPriceRuleId
     * @return CartPriceRuleDTO
     * @throws NotFoundHttpException
     */
    protected function getCartPriceRule($cartPriceRuleId)
    {
        return $this->getCartPriceRuleById($cartPriceRuleId)
            ->getCartPriceRuleDTO();
    }

    /**
     * @param $cartPriceRuleId
     * @return CartPriceRuleDTO
     * @throws NotFoundHttpException
     */
    protected function getCartPriceRuleWithAllData($cartPriceRuleId)
    {
        return $this->getCartPriceRuleById($cartPriceRuleId)
            ->getCartPriceRuleDTOWithAllData();
    }

    /**
     * @param string $cartPriceRuleId
     * @return GetCartPriceRuleResponse
     * @throws NotFoundHttpException
     */
    private function getCartPriceRuleById($cartPriceRuleId)
    {
        try {
            $request = new GetCartPriceRuleRequest($cartPriceRuleId);
            $response = new GetCartPriceRuleResponse();
            $this->dispatchQuery(new GetCartPriceRuleQuery($request, $response));
        } catch (EntityNotFoundException $e) {
            return abort(404);
        }

        return $response;
    }

    /**
     * @param $attributeId
     * @return AttributeDTO
     * @throws NotFoundHttpException
     */
    protected function getAttributeWithAllData($attributeId)
    {
        return $this->getAttributeById($attributeId)
            ->getAttributeDTOWithAttributeValues();
    }

    /**
     * @param $attributeId
     * @return AttributeDTO
     * @throws NotFoundHttpException
     */
    protected function getAttribute($attributeId)
    {
        return $this->getAttributeById($attributeId)
            ->getAttributeDTO();
    }

    /**
     * @param string $attributeId
     * @return GetAttributeResponse
     * @throws NotFoundHttpException
     */
    private function getAttributeById($attributeId)
    {
        try {
            $request = new GetAttributeRequest($attributeId);
            $response = new GetAttributeResponse();
            $this->dispatchQuery(new GetAttributeQuery($request, $response));
        } catch (EntityNotFoundException $e) {
            return abort(404);
        }

        return $response;
    }

    /**
     * @param $attributeValueId
     * @return AttributeValueDTO
     * @throws NotFoundHttpException
     */
    protected function getAttributeValueWithAllData($attributeValueId)
    {
        return $this->getAttributeValueById($attributeValueId)
            ->getAttributeValueDTOWithAllData();
    }

    /**
     * @param $attributeValueId
     * @return AttributeValueDTO
     * @throws NotFoundHttpException
     */
    protected function getAttributeValue($attributeValueId)
    {
        return $this->getAttributeValueById($attributeValueId)
            ->getAttributeValueDTO();
    }

    /**
     * @param string $attributeValueId
     * @return GetAttributeValueResponse
     * @throws NotFoundHttpException
     */
    private function getAttributeValueById($attributeValueId)
    {
        try {
            $request = new GetAttributeValueRequest($attributeValueId);
            $response = new GetAttributeValueResponse();
            $this->dispatchQuery(new GetAttributeValueQuery($request, $response));
        } catch (EntityNotFoundException $e) {
            return abort(404);
        }

        return $response;
    }

    /**
     * @param $couponId
     * @return CouponDTO
     * @throws NotFoundHttpException
     */
    protected function getCoupon($couponId)
    {
        return $this->getCouponById($couponId)
            ->getCouponDTO();
    }

    /**
     * @param string $couponId
     * @return GetCouponResponse
     * @throws NotFoundHttpException
     */
    private function getCouponById($couponId)
    {
        try {
            $request = new GetCouponRequest($couponId);
            $response = new GetCouponResponse();
            $this->dispatchQuery(new GetCouponQuery($request, $response));
        } catch (EntityNotFoundException $e) {
            return abort(404);
        }

        return $response;
    }

    /**
     * @param $catalogPromotionId
     * @return CatalogPromotionDTO
     * @throws NotFoundHttpException
     */
    protected function getCatalogPromotionWithAllData($catalogPromotionId)
    {
        return $this->getCatalogPromotionById($catalogPromotionId)
            ->getCatalogPromotionDTOWithAllData();
    }

    /**
     * @param $catalogPromotionId
     * @return CatalogPromotionDTO
     * @throws NotFoundHttpException
     */
    protected function getCatalogPromotion($catalogPromotionId)
    {
        return $this->getCatalogPromotionById($catalogPromotionId)
            ->getCatalogPromotionDTO();
    }

    /**
     * @param string $catalogPromotionId
     * @return GetCatalogPromotionResponse
     * @throws NotFoundHttpException
     */
    private function getCatalogPromotionById($catalogPromotionId)
    {
        try {
            $request = new GetCatalogPromotionRequest($catalogPromotionId);
            $response = new GetCatalogPromotionResponse($this->getPricing());
            $this->dispatchQuery(new GetCatalogPromotionQuery($request, $response));
        } catch (EntityNotFoundException $e) {
            return abort(404);
        }

        return $response;
    }

    /**
     * @param $tagId
     * @return TagDTO
     * @throws NotFoundHttpException
     */
    protected function getTag($tagId)
    {
        return $this->getTagById($tagId)
            ->getTagDTO();
    }

    /**
     * @param $tagId
     * @return TagDTO
     * @throws NotFoundHttpException
     */
    protected function getTagWithAllData($tagId)
    {
        return $this->getTagById($tagId)
            ->getTagDTOWithAllData();
    }

    /**
     * @param string $tagId
     * @return GetTagResponse
     * @throws NotFoundHttpException
     */
    private function getTagById($tagId)
    {
        try {
            $request = new GetTagRequest($tagId);
            $response = new GetTagResponse($this->getPricing());
            $this->dispatchQuery(new GetTagQuery($request, $response));
        } catch (EntityNotFoundException $e) {
            return abort(404);
        }

        return $response;
    }

    /**
     * @param string $filePath
     */
    protected function serveFile($filePath)
    {
        if (!file_exists($filePath)) {
            abort(404);
        }

        header('Content-Length: ' . filesize($filePath));
        header('Content-type: ' . $this->getMimeType($filePath));
        header('Expires: ' . date('r', strtotime('now +1 week')));
        header('Last-Modified: ' . date('r', filemtime($filePath)));
        header('Cache-Control: max-age=604800');
        ob_clean();
        flush();

        readfile($filePath);
        exit;
    }

    protected function getMimeType(string $file): string
    {
        $mime_types = [
            "pdf" => "application/pdf",
            "zip" => "application/zip",
            "docx" => "application/msword",
            "doc" => "application/msword",
            "xls" => "application/vnd.ms-excel",
            "ppt" => "application/vnd.ms-powerpoint",
            "gif" => "image/gif",
            "png" => "image/png",
            "jpeg" => "image/jpg",
            "jpg" => "image/jpg",
            "mp3" => "audio/mpeg",
            "wav" => "audio/x-wav",
            "mpeg" => "video/mpeg",
            "mpg" => "video/mpeg",
            "mpe" => "video/mpeg",
            "mov" => "video/quicktime",
            "avi" => "video/x-msvideo",
            "3gp" => "video/3gpp",
            "css" => "text/css",
            "jsc" => "application/javascript",
            "js" => "application/javascript",
            "php" => "text/html",
            "htm" => "text/html",
            "html" => "text/html",
        ];

        $variable = explode('.', $file);

        $extension = strtolower(end($variable));

        return $mime_types[$extension];
    }

    /**
     * @param string $value
     * @return null|int
     */
    protected function getCentsOrNull($value)
    {
        if ($value === '') {
            return null;
        }

        return (int) ($value * 100);
    }

    /**
     * @param string $value
     * @return null|int
     */
    protected function getIntOrNull($value)
    {
        if ($value === '') {
            return null;
        }

        return (int) $value;
    }
    /**

     * @param string $value
     * @return null|float
     */
    protected function getFloatOrNull($value)
    {
        if ($value === '') {
            return null;
        }

        return (float) $value;
    }

    /**
     * @param string $value
     * @return null|string
     */
    protected function getStringOrNull($value)
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return (string) $value;
    }

    /**
     * @param array $dateTime
     * @return int|null
     */
    protected function getTimestampFromDateTimeTimezoneInput(array $dateTime)
    {
        $date = Arr::get($dateTime, 'date');
        $time = Arr::get($dateTime, 'time');
        $timezone = Arr::get($dateTime, 'timezone');

        if (trim($date . $time) !== '') {
            $startDateTime = new DateTime($date . ' ' . $time, new DateTimeZone($timezone));
            return $startDateTime->getTimestamp();
        }

        return null;
    }

    protected function mergeCart(UuidInterface $userId)
    {
        $sessionId = $this->getSessionId();
        $userCart = null;
        $sessionCart = null;

        try {
            $userCart = $this->getCartByUserId($userId->getHex());
        } catch (KommerceException $e) {
        }

        try {
            $sessionCart = $this->getCartFromSession();
        } catch (KommerceException $e) {
        }

        try {
            if ($userCart === null && $sessionCart !== null) {
                // SessionCart Exists
                $this->dispatch(new SetCartUserCommand(
                    $sessionCart->id->getHex(),
                    $userId->getHex()
                ));
            } elseif ($userCart !== null && $sessionCart === null) {
                // UserCart Exists
                $this->dispatch(new SetCartSessionIdCommand(
                    $userCart->id->getHex(),
                    $sessionId
                ));
            } elseif ($userCart !== null && $sessionCart !== null) {
                if ($userCart->id->equals($sessionCart->id)) {
                    return;
                }

                // Both Exist
                $this->dispatch(new CopyCartItemsCommand(
                    $sessionCart->id->getHex(),
                    $userCart->id->getHex()
                ));
                $this->dispatch(new RemoveCartCommand($sessionCart->id->getHex()));
                $this->dispatch(new SetCartSessionIdCommand(
                    $userCart->id->getHex(),
                    $sessionId
                ));
            }
        } catch (KommerceException $e) {
            $this->logError($e->getMessage());
            $this->flashGenericWarning('Unable to merge your existing cart.');
        }
    }

    /**
     * @param string $message
     */
    private function logError($message)
    {
        // TODO: Log error message to file
    }
}
