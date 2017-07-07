# Replace Files

This plugin allows you to upload a replacement for an existing attachment.

## Cache Busting

Replacements use the same filename as the original, allowing existing links to continue working. However, this may require busting the cache to expose the new image. You can use the `replace_files.merge_replacement.replaced` action to handle this:

```php
add_action( 'replace_files.merge_replacement.replaced', function ( $post_id ) {
	// Purge the server's cache.
	wp_remote_request( wp_get_attachment_url( $post_id ), [
		'method' => 'PURGE',
	]);
});
```
