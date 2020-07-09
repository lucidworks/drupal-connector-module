## INTRODUCTION

The `fusion_connector` module, together with the Fusion 5 `Drupal 8 Datasource Connector`, allows you to index the content from a
Drupal 8/9 website into Lucidworks Fusion.

It is built on top of the [**JSON:API**](https://www.drupal.org/project/jsonapi) core module and it provides the
ability to filter the exposed data by **JSON:API** core module.

The module will expose a new route, `/fusion` which will display the filtered data in JSON:API format, while
the default `/jsonapi` route will work as expected, without being affected by the new module.

The current features of the Fusion Connector module:

 * Enable and disable resources.
 * Disable fields.
 * Disable entities for a language
 * Disable a specific entity for a specific language
 * Disable entities for a use role (if it already has the **access content** permission)

## REQUIREMENTS

The module will install the JSON:API 2.x Drupal core module.
Visit /admin/config/services/fusion_connector to overwrite and configure your API.

## INSTALLATION

Install `fusion_connector` using a standard method for installing a contributed Drupal module.

## INTEGRATION

In `Lucidworks Fusion 5`, when adding a new datasource, use the Drupal 8 connector.
When configuring the connector, you will have to fill in the following parameters:

- `Drupal URL` will provide the host of the website you want to index (https://www.example.com)
- `Username for login` will provide the username when the connector is authenticating on the website.
- `Password for login` will provide the password when the connector is authenticating on the website.

Since you can configure the module to expose different resources per user, you can create multiple datasources with
different usernames. This allows you to create multiple queries with separate access to resources.


## CONFIGURATION

Fusion_connector provides a configuration page where users with the "administer site configuration" permission can
filter the exposed data.
User permissions on the exposed resources can be managed in the Permissions page.

### URL Structure

A fusion_connector URL looks like this:

```
GET     /en/fusion/node/article
```

If you have enabled more languages, the fusion_connector route will be prefixed with the language code:
e.g. for Spanish, we'll have:

```
GET     /es/fusion/node/article
```

All the filtering, sorting and pagination parameters the JSON:API module provides are available. More details are
available on the [JSON:API documentation](https://www.drupal.org/docs/core-modules-and-themes/core-modules/jsonapi-module/jsonapi).

### Drupal 9
This module is compatible with Drupal 9.
