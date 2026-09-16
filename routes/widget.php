<?php

declare(strict_types=1);

use MosChat\Controllers\Api\Widget\WidgetController;

$router = app()->router();

$router->post('/api/widget/bootstrap', [WidgetController::class, 'bootstrap']);
$router->post('/api/widget/contact', [WidgetController::class, 'contact']);
$router->get('/api/widget/messages', [WidgetController::class, 'messages']);
$router->post('/api/widget/messages', [WidgetController::class, 'send']);
$router->post('/api/widget/typing', [WidgetController::class, 'typing']);
