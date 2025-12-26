# OrdersApi\DefaultApi

All URIs are relative to http://localhost:8001, except if the operation defines another base path.

| Method | HTTP request | Description |
| ------------- | ------------- | ------------- |
| [**cancelOrder()**](DefaultApi.md#cancelOrder) | **POST** /v1/orders/{id}/cancel | Cancel an order |
| [**createOrder()**](DefaultApi.md#createOrder) | **POST** /v1/orders | Create an order |
| [**getOrder()**](DefaultApi.md#getOrder) | **GET** /v1/orders/{id} | Get an order |
| [**getOrdersHealth()**](DefaultApi.md#getOrdersHealth) | **GET** /v1/orders/health | Health check |
| [**orderPaymentCaptured()**](DefaultApi.md#orderPaymentCaptured) | **POST** /v1/orders/{id}/events/payment-captured | Payment captured event |
| [**orderRefunded()**](DefaultApi.md#orderRefunded) | **POST** /v1/orders/{id}/events/refunded | Refunded event |


## `cancelOrder()`

```php
cancelOrder($id)
```

Cancel an order

Cancels an order, releases inventory reservation and processes refund if paid

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');



$apiInstance = new OrdersApi\Api\DefaultApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client()
);
$id = 'id_example'; // string | Order ID

try {
    $apiInstance->cancelOrder($id);
} catch (Exception $e) {
    echo 'Exception when calling DefaultApi->cancelOrder: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **id** | **string**| Order ID | |

### Return type

void (empty response body)

### Authorization

No authorization required

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: Not defined

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `createOrder()`

```php
createOrder($create_order_request): \OrdersApi\Model\Order
```

Create an order

Creates a new order, validates with menu service, reserves inventory, and creates payment intent

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');



$apiInstance = new OrdersApi\Api\DefaultApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client()
);
$create_order_request = new \OrdersApi\Model\CreateOrderRequest(); // \OrdersApi\Model\CreateOrderRequest

try {
    $result = $apiInstance->createOrder($create_order_request);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling DefaultApi->createOrder: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **create_order_request** | [**\OrdersApi\Model\CreateOrderRequest**](../Model/CreateOrderRequest.md)|  | |

### Return type

[**\OrdersApi\Model\Order**](../Model/Order.md)

### Authorization

No authorization required

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `getOrder()`

```php
getOrder($id): \OrdersApi\Model\Order
```

Get an order

Retrieves order details by ID

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');



$apiInstance = new OrdersApi\Api\DefaultApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client()
);
$id = 'id_example'; // string | Order ID

try {
    $result = $apiInstance->getOrder($id);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling DefaultApi->getOrder: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **id** | **string**| Order ID | |

### Return type

[**\OrdersApi\Model\Order**](../Model/Order.md)

### Authorization

No authorization required

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `getOrdersHealth()`

```php
getOrdersHealth(): \OrdersApi\Model\GetOrdersHealth200Response
```

Health check

Returns the health status of the orders service

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');



$apiInstance = new OrdersApi\Api\DefaultApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client()
);

try {
    $result = $apiInstance->getOrdersHealth();
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling DefaultApi->getOrdersHealth: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

This endpoint does not need any parameter.

### Return type

[**\OrdersApi\Model\GetOrdersHealth200Response**](../Model/GetOrdersHealth200Response.md)

### Authorization

No authorization required

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `orderPaymentCaptured()`

```php
orderPaymentCaptured($id)
```

Payment captured event

Webhook for payment captured event - commits inventory and marks order as paid

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');



$apiInstance = new OrdersApi\Api\DefaultApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client()
);
$id = 'id_example'; // string | Order ID

try {
    $apiInstance->orderPaymentCaptured($id);
} catch (Exception $e) {
    echo 'Exception when calling DefaultApi->orderPaymentCaptured: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **id** | **string**| Order ID | |

### Return type

void (empty response body)

### Authorization

No authorization required

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: Not defined

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `orderRefunded()`

```php
orderRefunded($id)
```

Refunded event

Webhook for refund event - marks order as canceled

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');



$apiInstance = new OrdersApi\Api\DefaultApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client()
);
$id = 'id_example'; // string | Order ID

try {
    $apiInstance->orderRefunded($id);
} catch (Exception $e) {
    echo 'Exception when calling DefaultApi->orderRefunded: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **id** | **string**| Order ID | |

### Return type

void (empty response body)

### Authorization

No authorization required

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: Not defined

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)
