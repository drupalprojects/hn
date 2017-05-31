# Headless drupal module

## From beginning to end

- User makes request to /url?
- hn NodeRestResource get()
- getFullNode (FieldTrait.php)
- hn.module hook_alter_entity_reference_data
