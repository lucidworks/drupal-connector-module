## INTRODUCTION

The fusion_connector module provides the ability to filter the exposed data by **JSON:API** core module.

The module will expose a new route, **/fusion** which will display the filtered data in JSON:API format, while the default **/jsonapi** route will work as expected, without being affected by the new module. 

The current features of the fusion_connector:

 * Enable and disable entities.
 * Disable fields.
 * Disable entities for a language
 * Disable entities for a use role (if it already have the **access content** permission)

## REQUIREMENTS

The module will install the JSON:API Drupal core module. 
Visit /admin/config/services/fusion_connector to overwrite and configure your API.

## INSTALLATION

Install fusion_connector using a standard method for installing a contributed Drupal module.

## CONFIGURATION

Fusion_connector provides an administration page where users with the "administer site configuration" permission can filter the exposed data.

### URL Structure

A fusion_connector URL looks like this:

```
GET     /en/fusion/node/article
```

If you have enabled more languages, the fusion_connector route will be prefixed with the language code, for example for Spanish, we'll have:

```
GET     /es/fusion/node/article
```

