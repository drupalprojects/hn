# Headless drupal module

## From beginning to end

- User makes request to /url?
- api_url NodeRestResource get()
- getFullNode (FieldTrait.php)
- api_url.module hook_alter_entity_reference_data
