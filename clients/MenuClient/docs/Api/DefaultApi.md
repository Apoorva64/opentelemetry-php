# MenuApi\DefaultApi

All URIs are relative to http://localhost:8000, except if the operation defines another base path.

| Method | HTTP request | Description |
| ------------- | ------------- | ------------- |
| [**createMenuItem()**](DefaultApi.md#createMenuItem) | **POST** /v1/menu/items | Create a menu item |
| [**getMenuHealth()**](DefaultApi.md#getMenuHealth) | **GET** /v1/menu/health | Health check |
| [**getMenuItem()**](DefaultApi.md#getMenuItem) | **GET** /v1/menu/items/{id} | Get a menu item |
| [**listMenuItems()**](DefaultApi.md#listMenuItems) | **GET** /v1/menu/items | List all menu items |
| [**updateMenuItem()**](DefaultApi.md#updateMenuItem) | **PATCH** /v1/menu/items/{id} | Update a menu item |
| [**updateMenuItemAvailability()**](DefaultApi.md#updateMenuItemAvailability) | **POST** /v1/menu/items/{id}/availability | Update item availability |
| [**validateMenuItems()**](DefaultApi.md#validateMenuItems) | **POST** /v1/menu/validation | Validate menu items |


## `createMenuItem()`

```php
createMenuItem($create_menu_item_request): \MenuApi\Model\MenuItem
```

Create a menu item

Creates a new menu item

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');



$apiInstance = new MenuApi\Api\DefaultApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client()
);
$create_menu_item_request = new \MenuApi\Model\CreateMenuItemRequest(); // \MenuApi\Model\CreateMenuItemRequest

try {
    $result = $apiInstance->createMenuItem($create_menu_item_request);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling DefaultApi->createMenuItem: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **create_menu_item_request** | [**\MenuApi\Model\CreateMenuItemRequest**](../Model/CreateMenuItemRequest.md)|  | |

### Return type

[**\MenuApi\Model\MenuItem**](../Model/MenuItem.md)

### Authorization

No authorization required

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `getMenuHealth()`

```php
getMenuHealth(): \MenuApi\Model\GetMenuHealth200Response
```

Health check

Returns the health status of the menu service

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');



$apiInstance = new MenuApi\Api\DefaultApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client()
);

try {
    $result = $apiInstance->getMenuHealth();
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling DefaultApi->getMenuHealth: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

This endpoint does not need any parameter.

### Return type

[**\MenuApi\Model\GetMenuHealth200Response**](../Model/GetMenuHealth200Response.md)

### Authorization

No authorization required

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `getMenuItem()`

```php
getMenuItem($id): \MenuApi\Model\MenuItem
```

Get a menu item

Returns a single menu item by ID

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');



$apiInstance = new MenuApi\Api\DefaultApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client()
);
$id = 'id_example'; // string | Menu item ID

try {
    $result = $apiInstance->getMenuItem($id);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling DefaultApi->getMenuItem: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **id** | **string**| Menu item ID | |

### Return type

[**\MenuApi\Model\MenuItem**](../Model/MenuItem.md)

### Authorization

No authorization required

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `listMenuItems()`

```php
listMenuItems(): \MenuApi\Model\ListMenuItems200Response
```

List all menu items

Returns a list of all available menu items

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');



$apiInstance = new MenuApi\Api\DefaultApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client()
);

try {
    $result = $apiInstance->listMenuItems();
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling DefaultApi->listMenuItems: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

This endpoint does not need any parameter.

### Return type

[**\MenuApi\Model\ListMenuItems200Response**](../Model/ListMenuItems200Response.md)

### Authorization

No authorization required

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `updateMenuItem()`

```php
updateMenuItem($id, $update_menu_item_request)
```

Update a menu item

Partially updates an existing menu item

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');



$apiInstance = new MenuApi\Api\DefaultApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client()
);
$id = 'id_example'; // string | Menu item ID
$update_menu_item_request = new \MenuApi\Model\UpdateMenuItemRequest(); // \MenuApi\Model\UpdateMenuItemRequest

try {
    $apiInstance->updateMenuItem($id, $update_menu_item_request);
} catch (Exception $e) {
    echo 'Exception when calling DefaultApi->updateMenuItem: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **id** | **string**| Menu item ID | |
| **update_menu_item_request** | [**\MenuApi\Model\UpdateMenuItemRequest**](../Model/UpdateMenuItemRequest.md)|  | [optional] |

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

## `updateMenuItemAvailability()`

```php
updateMenuItemAvailability($id, $update_menu_item_availability_request)
```

Update item availability

Updates availability status of a menu item and reconciles with inventory

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');



$apiInstance = new MenuApi\Api\DefaultApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client()
);
$id = 'id_example'; // string | Menu item ID
$update_menu_item_availability_request = new \MenuApi\Model\UpdateMenuItemAvailabilityRequest(); // \MenuApi\Model\UpdateMenuItemAvailabilityRequest

try {
    $apiInstance->updateMenuItemAvailability($id, $update_menu_item_availability_request);
} catch (Exception $e) {
    echo 'Exception when calling DefaultApi->updateMenuItemAvailability: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **id** | **string**| Menu item ID | |
| **update_menu_item_availability_request** | [**\MenuApi\Model\UpdateMenuItemAvailabilityRequest**](../Model/UpdateMenuItemAvailabilityRequest.md)|  | |

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

## `validateMenuItems()`

```php
validateMenuItems($validate_menu_items_request): \MenuApi\Model\ValidateMenuItems200Response
```

Validate menu items

Validates a list of menu items for availability and pricing

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');



$apiInstance = new MenuApi\Api\DefaultApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client()
);
$validate_menu_items_request = new \MenuApi\Model\ValidateMenuItemsRequest(); // \MenuApi\Model\ValidateMenuItemsRequest

try {
    $result = $apiInstance->validateMenuItems($validate_menu_items_request);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling DefaultApi->validateMenuItems: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **validate_menu_items_request** | [**\MenuApi\Model\ValidateMenuItemsRequest**](../Model/ValidateMenuItemsRequest.md)|  | |

### Return type

[**\MenuApi\Model\ValidateMenuItems200Response**](../Model/ValidateMenuItems200Response.md)

### Authorization

No authorization required

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)
