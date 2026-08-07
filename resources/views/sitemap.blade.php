<?php
/**
 * XML sitemap — one <url> per public, crawlable page: the homepage, the
 * fleet listing, and every available vehicle's detail page. Only
 * `available` vehicles are included (a `maintenance`-status vehicle already
 * 404s on its detail page, so listing it here would be a soft-404).
 *
 * Served at /sitemap.xml via the route in routes/web.php.
 */
?>
<?php echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n"; ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc>{{ url('/') }}</loc>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>
    <url>
        <loc>{{ route('vehicles.index') }}</loc>
        <changefreq>daily</changefreq>
        <priority>0.9</priority>
    </url>
    @foreach ($vehicles as $vehicle)
        <url>
            <loc>{{ route('vehicles.show', $vehicle) }}</loc>
            <changefreq>weekly</changefreq>
            <priority>0.8</priority>
        </url>
    @endforeach
</urlset>
