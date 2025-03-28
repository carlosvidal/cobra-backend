<?php

namespace Config;

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

$routes->setDefaultNamespace('App\Controllers');
$routes->setDefaultController('Home');
$routes->setDefaultMethod('index');
$routes->setTranslateURIDashes(false);
$routes->set404Override();
$routes->setAutoRoute(false);

// Public Routes (No auth required)
$routes->get('/', 'Home::index');
$routes->get('auth/login', 'Auth::login');
$routes->post('auth/login', 'Auth::login');
$routes->get('auth/logout', 'Auth::logout');
$routes->get('auth/forgot-password', 'Auth::forgot_password');
$routes->post('auth/forgot-password', 'Auth::forgot_password');
$routes->get('auth/reset-password/(:segment)', 'Auth::reset_password/$1');
$routes->post('auth/reset-password/(:segment)', 'Auth::reset_password/$1');

// Debug routes
$routes->get('debug/client-create', 'Debug::clientCreate');
$routes->post('debug/client-create', 'Debug::clientCreate');
$routes->get('debug/auth-info', 'Debug::authInfo');
$routes->get('debug/get-users-by-organization/(:num)', 'Debug::getUsersByOrganization/$1');
$routes->get('debug/get-clients-by-organization/(:num)', 'Debug::getClientsByOrganization/$1');
$routes->get('debug/orgContext', 'Debug::orgContext');
$routes->get('debug/csrf', 'Debug::csrf');
$routes->get('debug/db-test', 'Debug::dbTest');
$routes->get('debug/test-api', 'Debug::testApi');

// API Routes
$routes->group('api', ['namespace' => 'App\Controllers\Api'], function ($routes) {
    // Auth Public Routes
    $routes->post('auth/request-otp', 'AuthController::requestOtp');
    $routes->post('auth/verify-otp', 'AuthController::verifyOtp');
    $routes->post('auth/refresh-token', 'AuthController::refreshToken');
    // OPTIONS routes for CORS preflight
    $routes->match(['options'], 'auth/request-otp', 'AuthController::requestOtp');
    $routes->match(['options'], 'auth/verify-otp', 'AuthController::verifyOtp');
    $routes->match(['options'], 'auth/refresh-token', 'AuthController::refreshToken');

    // Ligo Webhook Route - Public
    $routes->post('webhooks/ligo', 'LigoWebhookController::handlePaymentNotification');
    $routes->match(['options'], 'webhooks/ligo', 'LigoWebhookController::handlePaymentNotification');
    
    // Ligo Auth Route - Public
    $routes->get('auth/ligo/token', 'LigoAuthController::getToken');
    $routes->match(['options'], 'auth/ligo/token', 'LigoAuthController::getToken');

    // Organization Routes
    $routes->match(['get', 'options'], 'organizations/(:segment)/clients', 'OrganizationController::clients/$1');

    // Client Routes
    $routes->match(['get', 'options'], 'clients', 'ClientController::index');
    $routes->match(['post', 'options'], 'clients', 'ClientController::create');
    $routes->match(['get', 'options'], 'clients/(:segment)', 'ClientController::show/$1');
    $routes->match(['put', 'options'], 'clients/(:segment)', 'ClientController::update/$1');
    $routes->match(['delete', 'options'], 'clients/(:segment)', 'ClientController::delete/$1');
    $routes->match(['get', 'options'], 'clients/uuid/(:segment)', 'ClientController::findByUuid/$1');
    $routes->match(['get', 'options'], 'clients/external/(:segment)', 'ClientController::findByExternalId/$1');
    $routes->match(['get', 'options'], 'clients/document/(:segment)', 'ClientController::findByDocument/$1');

    // Portfolio Routes
    $routes->match(['get', 'options'], 'portfolios', 'PortfolioController::index');
    $routes->match(['get', 'options'], 'portfolios/(:segment)', 'PortfolioController::show/$1');
});

// API Routes - Protected 
$routes->group('api', ['namespace' => 'App\Controllers\Api', 'filter' => 'api-auth'], function ($routes) {
    // Auth Protected Routes
    $routes->match(['post', 'options'], 'auth/logout', 'AuthController::logout');
    
    // Mobile App Routes
    $routes->match(['get', 'options'], 'portfolio/invoices', 'PortfolioController::myInvoices');
    $routes->match(['get', 'options'], 'portfolio/my', 'PortfolioController::myPortfolios');
    $routes->match(['get', 'options'], 'payments/search-invoices', 'PaymentController::searchInvoices');
    $routes->match(['post', 'options'], 'payments/register', 'PaymentController::registerMobilePayment');
    $routes->match(['get', 'options'], 'payments/generate-qr/(:num)', 'LigoPaymentController::generateQR/$1');
    
    // Invoice Routes
    $routes->match(['get', 'options'], 'invoices', 'InvoiceController::index');
    $routes->match(['get', 'options'], 'invoices/(:segment)', 'InvoiceController::show/$1');
    $routes->match(['get', 'options'], 'invoices/external/(:segment)', 'InvoiceController::findByExternalId');
    $routes->match(['get', 'options'], 'invoices/overdue', 'InvoiceController::overdue');
    
    // Payment Routes
    $routes->match(['get', 'options'], 'payments', 'PaymentController::index');
    $routes->match(['get', 'options'], 'payments/(:segment)', 'PaymentController::show/$1');
    $routes->match(['get', 'options'], 'payments/external/(:segment)', 'PaymentController::findByExternalId');
});

// Web Routes - Protected
$routes->group('', ['namespace' => 'App\Controllers', 'filter' => 'auth'], function ($routes) {
    // Dashboard Routes
    $routes->get('dashboard', 'Dashboard::index');

    // Organization Routes
    $routes->group('organizations', function ($routes) {
        $routes->get('/', 'OrganizationController::index');
        $routes->get('create', 'OrganizationController::create');
        $routes->post('/', 'OrganizationController::store');
        $routes->get('(:segment)', 'OrganizationController::view/$1');
        $routes->get('(:segment)/edit', 'OrganizationController::edit/$1');
        $routes->post('(:segment)', 'OrganizationController::update/$1');
        $routes->post('(:segment)/delete', 'OrganizationController::delete/$1');
    });

    // User Routes
    $routes->group('users', function ($routes) {
        $routes->get('/', 'UserController::index');
        $routes->get('create', 'UserController::create');
        $routes->post('/', 'UserController::store');
        $routes->post('(:segment)/delete', 'UserController::delete/$1');
        $routes->post('(:segment)', 'UserController::update/$1');
        $routes->get('(:segment)/edit', 'UserController::edit/$1');
        $routes->get('(:segment)', 'UserController::view/$1');
    });

    // Client Routes
    $routes->group('clients', function ($routes) {
        $routes->get('/', 'ClientController::index');
        $routes->get('create', 'ClientController::create');
        $routes->post('/', 'ClientController::store');
        $routes->get('import', 'ClientController::import');
        $routes->post('import', 'ClientController::import');
        $routes->get('(:segment)/edit', 'ClientController::edit/$1');
        $routes->post('(:segment)', 'ClientController::update/$1');
        $routes->post('(:segment)/delete', 'ClientController::delete/$1');
        $routes->get('(:segment)', 'ClientController::view/$1');
    });

    // Invoice Routes
    $routes->group('invoices', function ($routes) {
        $routes->get('/', 'InvoiceController::index');
        $routes->get('create', 'InvoiceController::create');
        $routes->post('create', 'InvoiceController::create');
        $routes->get('view/(:segment)', 'InvoiceController::view/$1');
        $routes->get('edit/(:segment)', 'InvoiceController::edit/$1');
        $routes->post('edit/(:segment)', 'InvoiceController::edit/$1');
        $routes->get('delete/(:segment)', 'InvoiceController::delete/$1');
        // Invoice import routes (with CSRF bypass)
        $routes->get('import', 'InvoiceController::import', ['csrf' => false]);
        $routes->post('import', 'InvoiceController::import', ['csrf' => false]);
    });

    // Portfolio Routes
    $routes->group('portfolios', function ($routes) {
        $routes->get('/', 'PortfolioController::index');
        $routes->get('create', 'PortfolioController::create');
        $routes->post('create', 'PortfolioController::create');
        $routes->get('(:segment)', 'PortfolioController::view/$1');
        $routes->get('(:segment)/edit', 'PortfolioController::edit/$1');
        $routes->post('(:segment)/edit', 'PortfolioController::edit/$1');
        $routes->post('(:segment)/delete', 'PortfolioController::delete/$1');
        $routes->get('organization/(:segment)/users', 'PortfolioController::getOrganizationUsers/$1');
        $routes->get('organization/(:segment)/clients', 'PortfolioController::getOrganizationClients/$1');
    });

    // Payment Routes
    $routes->group('payments', ['namespace' => 'App\Controllers'], function ($routes) {
        $routes->get('/', 'PaymentController::index');
        $routes->get('create', 'PaymentController::create');
        $routes->get('create/(:segment)', 'PaymentController::create/$1');
        $routes->post('create', 'PaymentController::create');
        $routes->get('view/(:segment)', 'PaymentController::view/$1');
        $routes->get('search-invoices', 'PaymentController::searchInvoices');
    });

    // Ligo Payment Routes
    $routes->group('payment/ligo', function ($routes) {
        $routes->get('qr/(:num)', 'LigoPaymentController::showQRPage/$1');
        $routes->get('generate-qr/(:num)', 'LigoPaymentController::generateQR/$1');
    });

    // Webhook Routes
    $routes->group('webhooks', function ($routes) {
        $routes->get('/', 'WebhookController::index');
        $routes->get('(:num)', 'WebhookController::view/$1');
        $routes->get('(:num)/test', 'WebhookController::test/$1');
        $routes->get('(:num)/retry', 'WebhookController::retry/$1');
    });

    // Ruta para obtener clientes por organización
    $routes->get('organizations/(:segment)/clients', 'OrganizationController::getClientsByOrganization/$1');
});
