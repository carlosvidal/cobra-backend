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

// Do not auto-route directories
$routes->setAutoRoute(false);

// API Routes
$routes->group('api', ['namespace' => 'App\Controllers\Api'], function ($routes) {
    // Auth API Routes
    $routes->match(['post', 'options'], 'auth/request-otp', 'Auth::requestOtp');
    $routes->match(['post', 'options'], 'auth/verify-otp', 'Auth::verifyOtp');
    $routes->match(['post', 'options'], 'auth/refresh-token', 'Auth::refreshToken');
    $routes->match(['post', 'options'], 'auth/logout', 'Auth::logout');
    
    // User API Routes
    $routes->get('user/profile', 'Users::profile');
    $routes->get('users', 'Users::index');
    $routes->get('users/portfolio/(:num)', 'Users::byPortfolio/$1');
    
    // Client API Routes
    $routes->get('clients', 'ClientController::index');
    $routes->get('clients/(:num)', 'ClientController::show/$1');
    $routes->get('clients/external/(:segment)', 'ClientController::findByExternalId/$1');
    $routes->get('clients/document/(:segment)', 'ClientController::findByDocument/$1');
    $routes->get('clients/uuid/(:segment)', 'ClientController::findByUuid/$1');
    
    // Portfolio API Routes
    $routes->get('portfolios', 'PortfolioController::index');
    $routes->get('portfolios/(:num)', 'PortfolioController::show/$1');
    $routes->get('portfolios/my', 'PortfolioController::myPortfolios');
    
    // Invoice API Routes
    $routes->get('invoices', 'InvoiceController::index');
    $routes->get('invoices/(:num)', 'InvoiceController::show/$1');
    $routes->post('invoices', 'InvoiceController::create');
    $routes->put('invoices/(:num)', 'InvoiceController::update/$1');
    $routes->delete('invoices/(:num)', 'InvoiceController::delete/$1');
    $routes->get('invoices/external/(:segment)', 'InvoiceController::findByExternalId/$1');
    $routes->get('invoices/overdue', 'InvoiceController::overdue');
    
    // Payment API Routes
    $routes->get('payments', 'PaymentController::index');
    $routes->get('payments/(:num)', 'PaymentController::show/$1');
    $routes->post('payments', 'PaymentController::create');
    $routes->put('payments/(:num)', 'PaymentController::update/$1');
    $routes->delete('payments/(:num)', 'PaymentController::delete/$1');
    $routes->get('payments/external/(:segment)', 'PaymentController::findByExternalId/$1');
});

// Web Routes
$routes->get('/', 'Home::index');

// Auth routes
$routes->get('auth/login', 'Auth::login');
$routes->post('auth/login', 'Auth::attemptLogin');
$routes->get('auth/logout', 'Auth::logout');

// Protected web routes
$routes->group('', ['filter' => 'auth'], function ($routes) {
    $routes->get('dashboard', 'Dashboard::index');
    
    // Users
    $routes->get('users', 'Users::index');
    $routes->get('users/create', 'Users::create');
    $routes->post('users/create', 'Users::store');
    $routes->get('users/edit/(:num)', 'Users::edit/$1');
    $routes->post('users/edit/(:num)', 'Users::update/$1');
    $routes->get('users/delete/(:num)', 'Users::delete/$1');

    // Organizations
    $routes->get('organizations', 'Organizations::index');
    $routes->get('organizations/create', 'Organizations::create');
    $routes->post('organizations/create', 'Organizations::store');
    $routes->get('organizations/edit/(:num)', 'Organizations::edit/$1');
    $routes->post('organizations/edit/(:num)', 'Organizations::update/$1');
    $routes->get('organizations/delete/(:num)', 'Organizations::delete/$1');

    // Portfolios
    $routes->get('portfolios', 'PortfolioController::index');
    $routes->get('portfolios/create', 'PortfolioController::create');
    $routes->post('portfolios/create', 'PortfolioController::store');
    $routes->get('portfolios/edit/(:num)', 'PortfolioController::edit/$1');
    $routes->post('portfolios/edit/(:num)', 'PortfolioController::update/$1');
    $routes->get('portfolios/delete/(:num)', 'PortfolioController::delete/$1');
    $routes->get('portfolios/view/(:num)', 'PortfolioController::view/$1');

    // Clients
    $routes->get('clients', 'ClientController::index');
    $routes->get('clients/create', 'ClientController::create');
    $routes->post('clients/create', 'ClientController::store');
    $routes->get('clients/edit/(:num)', 'ClientController::edit/$1');
    $routes->post('clients/edit/(:num)', 'ClientController::update/$1');
    $routes->get('clients/delete/(:num)', 'ClientController::delete/$1');
    $routes->get('clients/view/(:num)', 'ClientController::view/$1');
    $routes->get('clients/import', 'ClientController::import', ['csrf' => false]);
    $routes->post('clients/import', 'ClientController::import', ['csrf' => false]);

    // Invoices
    $routes->get('invoices', 'InvoiceController::index');
    $routes->get('invoices/create', 'InvoiceController::create');
    $routes->post('invoices/create', 'InvoiceController::store');
    $routes->get('invoices/edit/(:num)', 'InvoiceController::edit/$1');
    $routes->post('invoices/edit/(:num)', 'InvoiceController::update/$1');
    $routes->get('invoices/delete/(:num)', 'InvoiceController::delete/$1');
    $routes->get('invoices/view/(:num)', 'InvoiceController::view/$1');
    $routes->get('invoices/import', 'InvoiceController::import', ['csrf' => false]);
    $routes->post('invoices/import', 'InvoiceController::import', ['csrf' => false]);

    // Payments
    $routes->get('payments', 'PaymentController::index');
    $routes->get('payments/create', 'PaymentController::create');
    $routes->post('payments/create', 'PaymentController::store');
    $routes->get('payments/create/(:num)', 'PaymentController::create/$1');
    $routes->post('payments/create/(:num)', 'PaymentController::store/$1');
    $routes->get('payments/edit/(:num)', 'PaymentController::edit/$1');
    $routes->post('payments/edit/(:num)', 'PaymentController::update/$1');
    $routes->get('payments/delete/(:num)', 'PaymentController::delete/$1');
    $routes->get('payments/view/(:num)', 'PaymentController::view/$1');
    $routes->get('payments/report', 'PaymentController::report');

    // Webhooks
    $routes->get('webhooks', 'WebhookController::index');
    $routes->get('webhooks/create', 'WebhookController::create');
    $routes->post('webhooks/create', 'WebhookController::store');
    $routes->get('webhooks/edit/(:num)', 'WebhookController::edit/$1');
    $routes->post('webhooks/edit/(:num)', 'WebhookController::update/$1');
    $routes->get('webhooks/delete/(:num)', 'WebhookController::delete/$1');
    $routes->get('webhooks/logs/(:num)', 'WebhookController::logs/$1');
    $routes->get('webhooks/test/(:num)', 'WebhookController::test/$1');
    $routes->get('webhooks/retry/(:num)', 'WebhookController::retry/$1');
});
