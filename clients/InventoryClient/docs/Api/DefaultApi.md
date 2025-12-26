# InventoryApi\DefaultApi

All URIs are relative to http://localhost:8002, except if the operation defines another base path.

| Method | HTTP request | Description |
| ------------- | ------------- | ------------- |
| [**commitReservation()**](DefaultApi.md#commitReservation) | **POST** /v1/inventory/reservations/{id}/commit | Commit a reservation |
| [**createReservation()**](DefaultApi.md#createReservation) | **POST** /v1/inventory/reservations | Create a reservation |
| [**getInventoryHealth()**](DefaultApi.md#getInventoryHealth) | **GET** /v1/inventory/health | Health check |
| [**listStock()**](DefaultApi.md#listStock) | **GET** /v1/inventory/stock | List stock |
| [**reconcileInventory()**](DefaultApi.md#reconcileInventory) | **POST** /v1/inventory/reconcile | Reconcile inventory |
| [**releaseReservation()**](DefaultApi.md#releaseReservation) | **POST** /v1/inventory/reservations/{id}/release | Release a reservation |
| [**updateStock()**](DefaultApi.md#updateStock) | **PUT** /v1/inventory/stock/{itemId} | Update stock |


## `commitReservation()`

```php
commitReservation($id)
```

Commit a reservation

Commits a reservation - deducts stock and finalizes

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');



$apiInstance = new InventoryApi\Api\DefaultApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client()
);
$id = 'id_example'; // string | Reservation ID

try {
    $apiInstance->commitReservation($id);
} catch (Exception $e) {
    echo 'Exception when calling DefaultApi->commitReservation: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **id** | **string**| Reservation ID | |

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

## `createReservation()`

```php
createReservation($create_reservation_request): \InventoryApi\Model\CreateReservation201Response
```

Create a reservation

Creates a stock reservation for an order

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');



$apiInstance = new InventoryApi\Api\DefaultApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client()
);
$create_reservation_request = new \InventoryApi\Model\CreateReservationRequest(); // \InventoryApi\Model\CreateReservationRequest

try {
    $result = $apiInstance->createReservation($create_reservation_request);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling DefaultApi->createReservation: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **create_reservation_request** | [**\InventoryApi\Model\CreateReservationRequest**](../Model/CreateReservationRequest.md)|  | |

### Return type

[**\InventoryApi\Model\CreateReservation201Response**](../Model/CreateReservation201Response.md)

### Authorization

No authorization required

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `getInventoryHealth()`

```php
getInventoryHealth(): \InventoryApi\Model\GetInventoryHealth200Response
```

Health check

Returns the health status of the inventory service

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');



$apiInstance = new InventoryApi\Api\DefaultApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client()
);

try {
    $result = $apiInstance->getInventoryHealth();
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling DefaultApi->getInventoryHealth: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

This endpoint does not need any parameter.

### Return type

[**\InventoryApi\Model\GetInventoryHealth200Response**](../Model/GetInventoryHealth200Response.md)

### Authorization

No authorization required

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `listStock()`

```php
listStock()
```

List stock

Lists all stock items

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');



$apiInstance = new InventoryApi\Api\DefaultApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client()
);

try {
    $apiInstance->listStock();
} catch (Exception $e) {
    echo 'Exception when calling DefaultApi->listStock: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

This endpoint does not need any parameter.

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

## `reconcileInventory()`

```php
reconcileInventory($reconcile_inventory_request)
```

Reconcile inventory

Reconciles inventory based on menu item availability changes

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');



$apiInstance = new InventoryApi\Api\DefaultApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client()
);
$reconcile_inventory_request = new \InventoryApi\Model\ReconcileInventoryRequest(); // \InventoryApi\Model\ReconcileInventoryRequest

try {
    $apiInstance->reconcileInventory($reconcile_inventory_request);
} catch (Exception $e) {
    echo 'Exception when calling DefaultApi->reconcileInventory: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **reconcile_inventory_request** | [**\InventoryApi\Model\ReconcileInventoryRequest**](../Model/ReconcileInventoryRequest.md)|  | [optional] |

### Return type

void (empty response body)

### Authorization

No authorization required

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: Not defined

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `releaseReservation()`

```php
releaseReservation($id)
```

Release a reservation

Releases a reservation - returns reserved stock

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');



$apiInstance = new InventoryApi\Api\DefaultApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client()
);
$id = 'id_example'; // string | Reservation ID

try {
    $apiInstance->releaseReservation($id);
} catch (Exception $e) {
    echo 'Exception when calling DefaultApi->releaseReservation: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **id** | **string**| Reservation ID | |

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

## `updateStock()`

```php
updateStock($item_id, $update_stock_request)
```

Update stock

Updates stock quantity for an item

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');



$apiInstance = new InventoryApi\Api\DefaultApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client()
);
$item_id = 'item_id_example'; // string | Menu item ID
$update_stock_request = new \InventoryApi\Model\UpdateStockRequest(); // \InventoryApi\Model\UpdateStockRequest

try {
    $apiInstance->updateStock($item_id, $update_stock_request);
} catch (Exception $e) {
    echo 'Exception when calling DefaultApi->updateStock: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **item_id** | **string**| Menu item ID | |
| **update_stock_request** | [**\InventoryApi\Model\UpdateStockRequest**](../Model/UpdateStockRequest.md)|  | [optional] |

### Return type

void (empty response body)

### Authorization

No authorization required

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: Not defined

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)
