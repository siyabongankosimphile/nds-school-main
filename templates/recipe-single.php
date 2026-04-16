<?php
// Simple template to render a recipe by ID from our custom table
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$recipe_id = intval(get_query_var('nds_recipe_id'));

$table = $wpdb->prefix . 'nds_recipes';
$recipe = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $recipe_id));

get_header();
?>

<main id="primary" class="site-main">
    <style>
        .nds-container {max-width: 1100px; margin: 0 auto; padding: 24px 16px;}
        .nds-hero {position: relative; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,.08);}
        .nds-hero img {width: 100%; height: 420px; object-fit: cover; display: block;}
        .nds-title {font-size: 44px; line-height: 1.1; margin: 24px 0 10px; font-weight: 800;}
        .nds-meta {display: flex; gap: 14px; flex-wrap: wrap; margin-bottom: 24px;}
        .nds-pill {background: #f3f4f6; color: #374151; padding: 8px 12px; border-radius: 999px; font-size: 14px; display: inline-flex; align-items: center; gap: 8px;}
        .nds-lead {font-size: 18px; color: #4b5563; margin: 10px 0 28px;}
        .nds-grid {display: grid; grid-template-columns: 360px 1fr; gap: 28px;}
        .nds-card {background: #fff; border-radius: 14px; box-shadow: 0 8px 24px rgba(0,0,0,.06); padding: 22px;}
        .nds-card h3 {margin: 0 0 14px; font-size: 20px;}
        .nds-ingredients ul {list-style: none; padding: 0; margin: 0;}
        .nds-ingredients li {padding: 10px 0 10px 28px; position: relative; border-bottom: 1px solid #f1f5f9;}
        .nds-ingredients li:before {content: "✓"; color: #16a34a; position: absolute; left: 0; top: 10px; font-weight: 700;}
        .nds-steps ol {counter-reset: step; padding-left: 0;}
        .nds-steps li {list-style: none; counter-increment: step; padding: 14px 0 14px 44px; border-bottom: 1px solid #f1f5f9; position: relative;}
        .nds-steps li:before {content: counter(step); position: absolute; left: 0; top: 10px; width: 28px; height: 28px; border-radius: 50%; background: #2563eb; color: #fff; font-weight: 700; display: grid; place-items: center;}
        .nds-share {display: flex; gap: 10px; margin-top: 16px;}
        .nds-share a {text-decoration: none; border: 1px solid #e5e7eb; padding: 8px 12px; border-radius: 8px; color: #374151;}
        .nds-section {margin-top: 48px;}
        .nds-related-grid {display: grid; grid-template-columns: repeat(4, 1fr); gap: 18px;}
        .nds-related-card {background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 6px 20px rgba(0,0,0,.06); transition: transform .2s ease;}
        .nds-related-card:hover {transform: translateY(-4px);}        
        .nds-related-card img {width: 100%; height: 160px; object-fit: cover; display: block;}
        .nds-related-body {padding: 14px 14px 18px;}
        .nds-related-title {font-size: 16px; margin: 0 0 6px;}
        .nds-related-meta {font-size: 13px; color: #6b7280;}
        .nds-gallery{display:grid; grid-template-columns:repeat(4, 1fr); gap:12px;}
        .nds-gallery figure{margin:0; padding:0; border-radius:10px; overflow:hidden; background:#fff; box-shadow:0 6px 20px rgba(0,0,0,.06);}
        .nds-gallery img{width:100%; height:140px; object-fit:cover; display:block; transition:transform .25s ease;}
        .nds-gallery a:hover img{transform:scale(1.04);}        
        .nds-lightbox{position:fixed; inset:0; background:rgba(0,0,0,.8); display:none; align-items:center; justify-content:center; z-index:99999;}
        .nds-lightbox img{max-width:92vw; max-height:92vh; border-radius:8px;}
        .nds-lightbox-close{position:absolute; top:18px; right:22px; color:#fff; font-size:28px; cursor:pointer;}
        @media (max-width: 960px){ .nds-grid{grid-template-columns: 1fr;} .nds-gallery{grid-template-columns:repeat(2, 1fr);} }
        @media (max-width: 720px){ .nds-related-grid{grid-template-columns: repeat(2,1fr);} .nds-title{font-size:34px;} }
        @media (max-width: 480px){ .nds-related-grid{grid-template-columns: 1fr;} }
    </style>

    <div class="nds-container">
        <?php if ($recipe): ?>
            <?php
            $data = json_decode($recipe->the_recipe, true);
            $image_url = $recipe->image ? wp_get_attachment_url($recipe->image) : '';
            ?>

            <?php if (!empty($image_url)): ?>
                <div class="nds-hero"><img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($recipe->recipe_name); ?>"></div>
            <?php endif; ?>

            <h1 class="nds-title"><?php echo esc_html($recipe->recipe_name); ?></h1>

            <div class="nds-meta">
                <?php if (!empty($data['cooking'])): ?><span class="nds-pill">⏱ Cooking: <?php echo esc_html($data['cooking']); ?> min</span><?php endif; ?>
                <?php if (!empty($data['prep'])): ?><span class="nds-pill">🍳 Prep: <?php echo esc_html($data['prep']); ?> min</span><?php endif; ?>
                <?php if (!empty($data['servings'])): ?><span class="nds-pill">👥 Servings: <?php echo esc_html($data['servings']); ?></span><?php endif; ?>
            </div>

            <?php if (!empty($data['mini_description'])): ?>
                <p class="nds-lead"><?php echo esc_html($data['mini_description']); ?></p>
            <?php endif; ?>

            <div class="nds-grid">
                <?php if (!empty($data['ingredients'])): ?>
                    <aside class="nds-card nds-ingredients">
                        <h3>Ingredients</h3>
                        <ul>
                            <?php foreach ($data['ingredients'] as $ingredient): ?>
                                <li><?php echo esc_html($ingredient); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </aside>
                <?php endif; ?>

                <?php if (!empty($data['steps'])): ?>
                    <section class="nds-card nds-steps">
                        <h3>Instructions</h3>
                        <ol>
                            <?php foreach ($data['steps'] as $step): ?>
                                <li><?php echo esc_html($step); ?></li>
                            <?php endforeach; ?>
                        </ol>
                        <div class="nds-share">
                            <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode(get_permalink()); ?>" target="_blank" rel="noopener">Share</a>
                            <a href="mailto:?subject=<?php echo rawurlencode($recipe->recipe_name); ?>&body=<?php echo rawurlencode(get_permalink()); ?>">Email</a>
                            <a href="#" id="nds-download-pdf">Download PDF</a>
                        </div>
                    </section>
                <?php endif; ?>
            </div>

            <?php
            // Build gallery array
            $gallery_ids = [];
            if (!empty($recipe->gallery)) {
                $maybe_json = json_decode($recipe->gallery, true);
                if (is_array($maybe_json)) {
                    $gallery_ids = $maybe_json;
                } else {
                    $gallery_ids = array_filter(array_map('trim', explode(',', $recipe->gallery)));
                }
            }
            $gallery_urls = array();
            foreach ($gallery_ids as $gid) {
                $url = wp_get_attachment_url((int)$gid);
                if ($url) { $gallery_urls[] = $url; }
            }
            ?>

            <?php if (!empty($gallery_urls)): ?>
                <div class="nds-section">
                    <h2 style="font-size:28px; margin:0 0 14px; font-weight:700;">Gallery</h2>
                    <div class="nds-gallery">
                        <?php foreach ($gallery_urls as $gurl): ?>
                            <figure>
                                <a href="<?php echo esc_url($gurl); ?>" class="nds-lightbox-link"><img src="<?php echo esc_url($gurl); ?>" alt=""></a>
                            </figure>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="nds-lightbox" id="ndsLightbox" aria-hidden="true">
                    <span class="nds-lightbox-close" id="ndsLightboxClose">×</span>
                    <img id="ndsLightboxImg" src="" alt="">
                </div>
            <?php endif; ?>

            <?php
            // Related recipes (You may also like)
            $related = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE id != %d ORDER BY RAND() LIMIT 4", $recipe_id));

            // Helper to build recipe URL (post permalink if mapped, else fallback)
            $build_url = function($r) use ($wpdb, $table) {
                if (!empty($r->post_id) && get_post_status((int)$r->post_id)) {
                    $plink = get_permalink((int)$r->post_id);
                    if ($plink) return $plink;
                }
                return home_url('/recipe/' . intval($r->id));
            };
            ?>

            <?php if (!empty($related)): ?>
                <div class="nds-section">
                    <h2 style="font-size:28px; margin:0 0 14px; font-weight:700;">You may also like these recipes</h2>
                    <div class="nds-related-grid">
                        <?php foreach ($related as $r): ?>
                            <?php $rdata = json_decode($r->the_recipe, true); $rimg = $r->image ? wp_get_attachment_url($r->image) : ''; ?>
                            <a class="nds-related-card" href="<?php echo esc_url($build_url($r)); ?>">
                                <?php if ($rimg): ?><img src="<?php echo esc_url($rimg); ?>" alt="<?php echo esc_attr($r->recipe_name); ?>"><?php endif; ?>
                                <div class="nds-related-body">
                                    <div class="nds-related-title"><?php echo esc_html($r->recipe_name); ?></div>
                                    <div class="nds-related-meta">
                                        <?php if (!empty($rdata['cooking'])): ?>⏱ <?php echo esc_html($rdata['cooking']); ?> min · <?php endif; ?>
                                        <?php if (!empty($rdata['servings'])): ?>👥 <?php echo esc_html($rdata['servings']); ?> servings<?php endif; ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="nds-container"><div class="nds-card">Recipe not found.</div></div>
        <?php endif; ?>
    </div>
    <?php
    // Gather info for PDF template
    $custom_logo_id = get_theme_mod('custom_logo');
    $logo_url = $custom_logo_id ? wp_get_attachment_image_url($custom_logo_id, 'full') : get_site_icon_url(128);
    $site_name = get_bloginfo('name');
    $site_url = home_url('/');
    ?>
    <!-- Hidden HTML template used to render the PDF -->
    <div id="nds-pdf-template" style="width:794px; padding:30px 36px; margin:0 auto; background:#ffffff; color:#111827; font-family:Helvetica, Arial, sans-serif; display:none;">
        <style>
            .nds-pdf-header{display:flex; align-items:center; justify-content:space-between; border-bottom:2px solid #e5e7eb; padding-bottom:12px;}
            .nds-pdf-brand{display:flex; align-items:center; gap:14px;}
            .nds-pdf-brand img{height:48px; width:auto;}
            .nds-pdf-site{font-size:12px; color:#6b7280;}
            .nds-pdf-title{font-size:28px; font-weight:800; margin:18px 0 6px;}
            .nds-pdf-meta{font-size:12px; color:#374151; margin-bottom:14px}
            .nds-pdf-hero{width:100%; height:260px; border-radius:10px; overflow:hidden; margin:6px 0 16px; background-size:cover; background-position:center; background-repeat:no-repeat;}
            .nds-pdf-two-col{display:grid; grid-template-columns:280px 1fr; gap:20px;}
            .nds-pdf-card{background:#fafafa; border:1px solid #f3f4f6; border-radius:10px; padding:14px 16px;}
            .nds-pdf-card h3{margin:0 0 8px; font-size:14px;}
            .nds-pdf-ingredients ul{list-style:none; margin:0; padding:0;}
            .nds-pdf-ingredients li{padding:6px 0 6px 16px; position:relative; border-bottom:1px dashed #e5e7eb;}
            .nds-pdf-ingredients li:before{content:'•'; position:absolute; left:0; color:#2563eb; font-weight:700;}
            .nds-pdf-steps ol{counter-reset:step; padding-left:0; margin:0;}
            .nds-pdf-steps li{list-style:none; counter-increment:step; padding:8px 0 8px 26px; border-bottom:1px dashed #e5e7eb; position:relative;}
            .nds-pdf-steps li:before{content:counter(step); position:absolute; left:0; top:8px; background:#2563eb; color:#fff; width:18px; height:18px; display:grid; place-items:center; border-radius:50%; font-size:11px; font-weight:700;}
            .nds-pdf-footer{border-top:2px solid #e5e7eb; margin-top:16px; padding-top:10px; display:flex; align-items:center; justify-content:space-between; font-size:11px; color:#6b7280}
        </style>
        <div class="nds-pdf-header">
            <div class="nds-pdf-brand">
                <?php if ($logo_url): ?><img src="<?php echo esc_url($logo_url); ?>" alt="Logo"><?php endif; ?>
                <div>
                    <div style="font-weight:700;"><?php echo esc_html($site_name); ?></div>
                    <div class="nds-pdf-site"><?php echo esc_html($site_url); ?></div>
                </div>
            </div>
            <div class="nds-pdf-site">Recipe PDF</div>
        </div>
        <div class="nds-pdf-title"><?php echo esc_html($recipe->recipe_name ?? 'Recipe'); ?></div>
        <div class="nds-pdf-meta">
            <?php if (!empty($data['cooking'])): ?>⏱ Cooking: <?php echo esc_html($data['cooking']); ?> min &nbsp;&nbsp;<?php endif; ?>
            <?php if (!empty($data['prep'])): ?>🍳 Prep: <?php echo esc_html($data['prep']); ?> min &nbsp;&nbsp;<?php endif; ?>
            <?php if (!empty($data['servings'])): ?>👥 Servings: <?php echo esc_html($data['servings']); ?><?php endif; ?>
        </div>
        <?php if (!empty($image_url)): ?>
            <div class="nds-pdf-hero" style="background-image:url('<?php echo esc_url($image_url); ?>');"></div>
        <?php endif; ?>
        <div class="nds-pdf-two-col">
            <?php if (!empty($data['ingredients'])): ?>
                <div class="nds-pdf-card nds-pdf-ingredients">
                    <h3>Ingredients</h3>
                    <ul>
                        <?php foreach ($data['ingredients'] as $ingredient): ?>
                            <li><?php echo esc_html($ingredient); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <?php if (!empty($data['steps'])): ?>
                <div class="nds-pdf-card nds-pdf-steps">
                    <h3>Instructions</h3>
                    <ol>
                        <?php foreach ($data['steps'] as $step): ?>
                            <li><?php echo esc_html($step); ?></li>
                        <?php endforeach; ?>
                    </ol>
                </div>
            <?php endif; ?>
        </div>
        <div class="nds-pdf-footer">
            <div>© <?php echo date('Y'); ?> <?php echo esc_html($site_name); ?></div>
            <div><?php echo esc_html($site_url); ?></div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
    <script>
    (function(){
        const btn = document.getElementById('nds-download-pdf');
        // Simple lightbox for gallery
        const lightbox = document.getElementById('ndsLightbox');
        const lightboxImg = document.getElementById('ndsLightboxImg');
        const lightboxClose = document.getElementById('ndsLightboxClose');
        if (lightbox && lightboxImg) {
            document.querySelectorAll('.nds-lightbox-link').forEach(function(a){
                a.addEventListener('click', function(ev){
                    ev.preventDefault();
                    lightboxImg.src = this.href;
                    lightbox.style.display = 'flex';
                    lightbox.setAttribute('aria-hidden','false');
                });
            });
            function closeBox(){ lightbox.style.display = 'none'; lightbox.setAttribute('aria-hidden','true'); lightboxImg.src = ''; }
            if (lightboxClose) lightboxClose.addEventListener('click', closeBox);
            if (lightbox) lightbox.addEventListener('click', function(e){ if (e.target === this) closeBox(); });
            document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeBox(); });
        }
        if (!btn) return;
        btn.addEventListener('click', async function(e){
            e.preventDefault();
            const node = document.getElementById('nds-pdf-template');
            if (!node) return;
            node.style.display = 'block';
            try {
                const canvas = await html2canvas(node, { scale: 2, useCORS: true, allowTaint: true, backgroundColor: '#ffffff' });
                const imgData = canvas.toDataURL('image/png');
                const { jsPDF } = window.jspdf;
                const pdf = new jsPDF('p', 'pt', 'a4');
                const pageWidth = pdf.internal.pageSize.getWidth();
                const pageHeight = pdf.internal.pageSize.getHeight();
                const imgWidth = pageWidth;
                const imgHeight = canvas.height * imgWidth / canvas.width;
                let heightLeft = imgHeight;
                let position = 0;
                pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
                heightLeft -= pageHeight;
                while (heightLeft > 0) {
                    position = -(imgHeight - heightLeft);
                    pdf.addPage();
                    pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
                    heightLeft -= pageHeight;
                }
                pdf.save('<?php echo esc_js(sanitize_title($recipe->recipe_name ?? 'recipe')); ?>.pdf');
            } catch (err) {
                console.error(err);
            } finally {
                node.style.display = 'none';
            }
        });
    })();
    </script>
</main>

<?php get_footer(); ?>


