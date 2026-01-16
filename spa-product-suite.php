/*
 * Título: Nakama Suite v23 (Price Mapping Fix) By ImperioDev
 * Descripción: Corrección crítica para leer 'Precio normal' ignorando 'Precio rebajado' en el CSV.
 */

// 1. CARGA DE RECURSOS
add_action('wp_enqueue_scripts', 'nakama_load_resources');
function nakama_load_resources() {
    if (current_user_can('administrator')) {
        wp_enqueue_media();
        wp_enqueue_script('jquery');
        
        $raw_attrs = wc_get_attribute_taxonomies();
        $attributes = [];
        foreach ($raw_attrs as $attr) {
            $slug = wc_attribute_taxonomy_name($attr->attribute_name);
            $terms = get_terms(['taxonomy' => $slug, 'hide_empty' => false]);
            $term_names = [];
            if (!is_wp_error($terms)) { foreach ($terms as $t) $term_names[] = $t->name; }
            $attributes[] = ['label' => $attr->attribute_label, 'slug' => $attr->attribute_name, 'terms' => $term_names];
        }

        $cats = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
        $tags = get_terms(['taxonomy' => 'product_tag', 'hide_empty' => false]);

        wp_localize_script('jquery', 'NK_DATA', ['cats' => $cats, 'tags' => $tags, 'attrs' => $attributes]);
    }
}

// 2. UI
add_action('wp_footer', 'nakama_render_app');
function nakama_render_app() {
    if (!current_user_can('administrator')) return;
    ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/PapaParse/5.4.1/papaparse.min.js"></script>

    <style>
        /* CSS */
		.imperiodev-anim {
        /* Color base intenso para que la rotación funcione bien */
        color: #ff0000; 
        /* La animación dura 4 segundos y es infinita */
        animation: color-cycle 4s linear infinite;
        /* Aseguramos que el elemento sea inline para no romper la frase */
        display: inline-block; 
    }

    @keyframes color-cycle {
        0% {
            filter: hue-rotate(0deg);
        }
        100% {
            /* Gira la rueda de colores 360 grados */
            filter: hue-rotate(360deg);
        }
    }
        #nk-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: #f4f6f8; z-index: 99990; overflow-y: auto; font-family: -apple-system, sans-serif; text-align: left; color: #3c434a; }
        .media-modal-backdrop { z-index: 999991 !important; } .media-modal { z-index: 999992 !important; }

        #nk-launcher { position: fixed; bottom: 80px; left: 20px; z-index: 99980; width: 55px; height: 55px; background: #d63638; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 4px 15px rgba(0,0,0,0.3); font-size: 28px; transition: transform 0.2s; }
        #nk-launcher:hover { transform: scale(1.1); background: #ff0000; }

        .nk-header { padding: 15px 40px; background: white; border-bottom: 1px solid #dcdcde; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .nk-container { padding: 40px; max-width: 1200px; margin: 0 auto; box-sizing: border-box; }
        
        #nk-start-screen { display: flex; gap: 20px; justify-content: center; padding: 40px 0; }
        .nk-action-card { background: white; border: 1px solid #c3c4c7; border-radius: 8px; padding: 40px; width: 300px; text-align: center; cursor: pointer; transition: all 0.2s; }
        .nk-action-card:hover { transform: translateY(-5px); border-color: #2271b1; }
        .nk-action-icon { font-size: 40px; display: block; margin-bottom: 15px; }
        .nk-action-title { font-size: 18px; font-weight: bold; margin-bottom: 10px; display:block;}

        #nk-manual-form { display: none; background: white; padding: 30px; border-radius: 8px; border: 1px solid #c3c4c7; max-width: 900px; margin: 0 auto; }
        .nk-form-row { display: flex; gap: 20px; margin-bottom: 15px; }
        .nk-form-group { flex: 1; position: relative; }
        .nk-form-label { display: block; font-size: 12px; font-weight: bold; margin-bottom: 5px; color: #50575e; }
        .nk-input { width: 100%; padding: 8px; border: 1px solid #8c8f94; border-radius: 4px; box-sizing: border-box; }
        .nk-input-fixed { background: #eef0f2; color: #646970; font-weight: 600; cursor: not-allowed; }
        
        .nk-search-box { width: 100%; padding: 6px; margin-bottom: 5px; border: 1px solid #ddd; border-radius: 4px; font-size: 12px; }
        .nk-checkbox-list { height: 100px; overflow-y: auto; border: 1px solid #8c8f94; padding: 5px; border-radius: 4px; background: #fff; }
        .nk-checkbox-item { display: block; font-size: 12px; margin-bottom: 3px; cursor: pointer; }
        
        .nk-generator-box { background: #f6f7f7; padding: 20px; border-radius: 6px; border: 1px solid #dcdcde; margin-bottom: 20px; }
        .nk-gen-header { font-weight: bold; color: #2271b1; margin-bottom: 15px; border-bottom: 1px solid #ddd; padding-bottom: 10px; display: flex; justify-content: space-between; align-items: center; }
        
        .nk-datalist-helper { position: absolute; top: 100%; left: 0; width: 100%; max-height: 150px; overflow-y: auto; background: white; border: 1px solid #2271b1; z-index: 50; display: none; box-shadow: 0 4px 10px rgba(0,0,0,0.1); border-radius: 0 0 4px 4px; }
        .nk-datalist-opt { padding: 8px; cursor: pointer; font-size: 12px; border-bottom: 1px solid #eee; }
        .nk-datalist-opt:hover { background: #2271b1; color: white; }

        .nk-table-wrapper { margin-top: 20px; border: 1px solid #ccc; background: white; border-radius: 4px; overflow: hidden; }
        .nk-table-header { background: #eee; padding: 10px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #ddd; }
        #nk-staging-table { width: 100%; border-collapse: collapse; font-size: 12px; }
        #nk-staging-table th { background: #f9f9f9; padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        #nk-staging-table td { padding: 8px; border-bottom: 1px solid #eee; }
        .nk-btn-remove { color: red; cursor: pointer; font-weight: bold; padding: 0 5px; }
        .nk-btn-clear { font-size: 11px; color: #d63638; cursor: pointer; background: white; border: 1px solid #d63638; padding: 2px 8px; border-radius: 4px; }

        .product-card { background: white; padding: 20px; margin-bottom: 20px; border-radius: 8px; border-left: 5px solid #ccc; display: flex; flex-direction: column; gap: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .product-card.type-variable { border-left-color: #826eb4; } .product-card.type-simple { border-left-color: #2271b1; }
        .nk-main-info { display: flex; gap: 20px; align-items: flex-start; padding-bottom: 15px; border-bottom: 1px solid #f0f0f1; }
        
        .nk-thumb-container { width: 80px; flex-shrink: 0; text-align: center; }
        .nk-thumb-box-lg { width: 80px; height: 80px; background: #eee; cursor: pointer; position: relative; border-radius: 4px; overflow: hidden; border: 1px solid #ddd; }
        .nk-thumb-box-lg img { width: 100%; height: 100%; object-fit: cover; }
        
        .nk-details { flex-grow: 1; display: flex; flex-direction: column; gap: 8px; min-width: 0; }
        .nk-row-input { width: 100%; padding: 6px 10px; border: 1px solid #8c8f94; border-radius: 4px; display: block; box-sizing: border-box; font-size: 13px; }
        
        .nk-desc-wrapper { border: 1px solid #eee; padding: 10px; background: #fcfcfc; border-radius: 4px; }
        .nk-desc-header { display:flex; justify-content:space-between; margin-bottom:5px; align-items: center; }
        .nk-desc-area { width: 100%; height: 60px; border: 1px solid #ccc; font-family: inherit; font-size: 12px; resize: vertical; box-sizing: border-box; }
        
        .nk-meta-row { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .nk-meta-tag { display: inline-flex; align-items: center; height: 24px; padding: 0 8px; font-size: 11px; color: #50575e; background: #f0f0f1; border: 1px solid #dcdcde; border-radius: 4px; white-space: nowrap; }
        .nk-btn-template { font-size: 10px !important; line-height: 1 !important; padding: 4px 8px !important; height: auto !important; width: auto !important; background: #fff; border: 1px solid #2271b1; color: #2271b1; border-radius: 3px; cursor: pointer; text-transform: uppercase; font-weight: 600; }
        .nk-btn-template:hover { background: #2271b1; color: white; }

        .nk-smart-area { background: #f8f9fa; padding: 15px; border: 1px dashed #ccc; border-radius: 6px; margin-top: 5px; }
        .nk-thumbs-grid { display: flex; gap: 10px; flex-wrap: wrap; }
        .nk-thumb-item { display: flex; align-items: center; gap: 8px; background: white; padding: 6px; border: 1px solid #c3c4c7; border-radius: 4px; }
        .nk-thumb-box-sm { width: 40px; height: 40px; background: #eee; cursor: pointer; border-radius: 3px; overflow: hidden; border: 1px solid #ddd; position: relative; }
        .nk-thumb-box-sm img { width: 100%; height: 100%; object-fit: cover; }
        
        .status-col { min-width: 100px; text-align: right; font-weight: bold; font-size: 12px; color: #666; }
        #nk-progress-bar-container { position: absolute; bottom: 0; left: 0; width: 100%; height: 4px; background: #f0f0f1; display: none; }
        #nk-progress-bar-fill { height: 100%; background: #008a20; width: 0%; transition: width 0.3s; }
    </style>

    <div id="nk-launcher" title="Abrir Nakama Suite">🚀</div>

    <div id="nk-modal">
<div class="nk-header">
    <div style="font-size: 20px; font-weight: bold;">
        ⚡ Nakama Suite Import Tool v23 by 
        <span class="imperiodev-anim">ImperioDev</span>
    </div>
    
    <div style="display:flex; gap:10px;">
        <button class="button" id="nk-reset-btn" style="display:none;">🏠 Inicio</button>
        <button class="button" onclick="jQuery('#nk-modal').fadeOut()">Cerrar</button>
        <button class="button button-primary" id="nk-process-btn" style="display:none;">Subir a WooCommerce</button>
    </div>
            <div id="nk-progress-bar-container"><div id="nk-progress-bar-fill"></div></div>
        </div>

        <div class="nk-container">
            <div id="nk-start-screen">
                <div class="nk-action-card" onclick="document.getElementById('nk-csv-input').click()">
                    <span class="nk-action-icon">📂</span>
                    <span class="nk-action-title">Importar CSV</span>
                    <input type="file" id="nk-csv-input" accept=".csv" style="display:none;" />
                </div>
                <div class="nk-action-card" id="nk-btn-manual-mode">
                    <span class="nk-action-icon">✨</span>
                    <span class="nk-action-title">Crear Manualmente</span>
                </div>
            </div>

            <div id="nk-manual-form">
                <h2 style="margin-top:0;">Nuevo Producto</h2>
                
                <div class="nk-form-row">
                    <div class="nk-form-group"><label class="nk-form-label">Nombre</label><input type="text" id="man-name" class="nk-input" placeholder="One Pisu"></div>
                    <div class="nk-form-group"><label class="nk-form-label">SKU Base (Padre)</label><input type="text" id="man-sku" class="nk-input" placeholder="OP-001"></div>
                </div>

                <div class="nk-form-row">
                    <div class="nk-form-group"><label class="nk-form-label">Categorías</label><input type="text" class="nk-search-box" placeholder="Buscar..." onkeyup="filterList(this, '#man-cats-list')"><div class="nk-checkbox-list" id="man-cats-list"></div></div>
                    <div class="nk-form-group"><label class="nk-form-label">Etiquetas</label><input type="text" class="nk-search-box" placeholder="Buscar..." onkeyup="filterList(this, '#man-tags-list')"><div class="nk-checkbox-list" id="man-tags-list"></div></div>
                </div>

                <div class="nk-generator-box">
                    <div class="nk-gen-header"><span>🛠️ Definir Variantes</span><button class="nk-btn-clear" id="nk-clear-inputs">Limpiar Campos</button></div>
                    
                    <div class="nk-form-row">
                        <div class="nk-form-group" style="flex:0.3;"><label class="nk-form-label">Atributo</label><input type="text" class="nk-input nk-input-fixed" value="Estilo" readonly></div>
                        <div class="nk-form-group"><label class="nk-form-label">Valores</label><input type="text" id="man-attr1-vals" class="nk-input" placeholder="Ej: Oversize, T-shirt" autocomplete="off"><div class="nk-datalist-helper" id="list-estilo"></div></div>
                    </div>
                    <div class="nk-form-row">
                        <div class="nk-form-group" style="flex:0.3;"><label class="nk-form-label">Atributo</label><input type="text" class="nk-input nk-input-fixed" value="Color" readonly></div>
                        <div class="nk-form-group"><label class="nk-form-label">Valores</label><input type="text" id="man-attr2-vals" class="nk-input" placeholder="Ej: Negro, Blanco" autocomplete="off"><div class="nk-datalist-helper" id="list-color"></div></div>
                    </div>
                    <div class="nk-form-row">
                        <div class="nk-form-group" style="flex:0.3;"><label class="nk-form-label">Atributo</label><input type="text" class="nk-input nk-input-fixed" value="Talla" readonly></div>
                        <div class="nk-form-group"><label class="nk-form-label">Valores</label><input type="text" id="man-attr3-vals" class="nk-input" placeholder="Ej: S, M, L" autocomplete="off"><div class="nk-datalist-helper" id="list-talla"></div></div>
                    </div>
                    
                    <div class="nk-form-row" style="align-items: flex-end; margin-top:20px;">
                        <div class="nk-form-group"><label class="nk-form-label">Precio Lote ($)</label><input type="number" id="man-batch-price" class="nk-input" placeholder="500"></div>
                        <div class="nk-form-group"><button class="button" id="nk-add-batch" style="width:100%;">➕ Añadir a Tabla</button></div>
                    </div>
                    
                    <div id="nk-staging-wrapper" class="nk-table-wrapper" style="display:none;">
                        <div class="nk-table-header"><strong style="font-size:12px;">Variantes Pendientes</strong><button class="nk-btn-clear" id="nk-clear-table">🗑️ Borrar</button></div>
                        <table id="nk-staging-table"><thead><tr><th>SKU</th><th>Atributos</th><th>Precio</th><th></th></tr></thead><tbody></tbody></table>
                    </div>
                </div>

                <button class="button button-primary button-large" id="nk-finalize-manual" style="width:100%;">✅ Crear Tarjeta</button>
            </div>

            <div id="nk-products-list" style="display:none;"></div>
        </div>
    </div>

    <script>
    var pendingVariations = [];
    const DEFAULT_SHIP = { weight: '0.25', len: '30', width: '25', height: '2' };
    const TEMPLATE_DESC = `✨ Fabricado con pasión en Nakama Bordados ✨\n\nCada uno de nuestros productos es elaborado cuidadosamente en nuestro taller, combinando bordado y estampado de alta calidad para ofrecerte piezas únicas, duraderas y llenas de estilo. Ya sea una prenda bordada o estampada, cuidamos cada detalle para garantizar un acabado profesional y resistente.`;

    function filterList(input, listId) {
        var filter = input.value.toUpperCase();
        var labels = document.querySelectorAll(listId + ' label');
        labels.forEach(l => {
            var txt = l.textContent || l.innerText;
            l.style.display = txt.toUpperCase().indexOf(filter) > -1 ? "" : "none";
        });
    }

    jQuery(document).ready(function($) {
        const NK_AJAX_URL = '<?php echo admin_url('admin-ajax.php'); ?>';
        let parsedProducts = []; 

        function init() {
            if(typeof NK_DATA !== 'undefined') {
                $('#man-cats-list').html(NK_DATA.cats.map(c => `<label class="nk-checkbox-item"><input type="checkbox" value="${c.name}" class="cat-chk"> ${c.name}</label>`).join(''));
                $('#man-tags-list').html(NK_DATA.tags.map(t => `<label class="nk-checkbox-item"><input type="checkbox" value="${t.name}" class="tag-chk"> ${t.name}</label>`).join(''));
                bindSuggestions('Estilo', '#list-estilo', '#man-attr1-vals');
                bindSuggestions('Color', '#list-color', '#man-attr2-vals');
                bindSuggestions('Talla', '#list-talla', '#man-attr3-vals');
            }
        }
        
        function bindSuggestions(keyword, listId, inputId) {
            let found = NK_DATA.attrs.find(a => a.label.toLowerCase().includes(keyword.toLowerCase()));
            if(found && found.terms.length > 0) {
                let html = found.terms.map(t => `<div class="nk-datalist-opt">${t}</div>`).join('');
                $(listId).html(html);
                $(inputId).focus(function(){ $(listId).show(); });
                $(listId).on('click', '.nk-datalist-opt', function() {
                    let cur = $(inputId).val(); let val = $(this).text();
                    $(inputId).val(cur ? cur + ', ' + val : val); $(listId).hide();
                });
                $(document).mouseup(function(e){ if(!$(listId).is(e.target) && $(listId).has(e.target).length === 0 && !$(inputId).is(e.target)) $(listId).hide(); });
            }
        }
        init();

        $('#nk-launcher').click(function() { $('#nk-modal').fadeIn(); });
        $('#nk-btn-manual-mode').click(function() { $('#nk-start-screen').hide(); $('#nk-manual-form').fadeIn(); $('#nk-reset-btn').show(); });
        $('#nk-clear-inputs').click(function() { $('#man-attr1-vals, #man-attr2-vals, #man-attr3-vals, #man-batch-price').val(''); });
        $('#nk-clear-table').click(function() { if(confirm('¿Borrar?')) { pendingVariations = []; renderStagingTable(); } });
        $('#nk-reset-btn').click(function() { if(confirm('¿Reiniciar?')) { parsedProducts = []; pendingVariations = []; $('#nk-products-list').empty().hide(); $('#nk-manual-form').hide(); $('#nk-start-screen').fadeIn(); $('#nk-process-btn').hide(); $('#nk-reset-btn').hide(); $('#nk-csv-input').val(''); renderStagingTable(); } });

        $('#nk-add-batch').click(function() {
            let price = $('#man-batch-price').val(); let skuBase = $('#man-sku').val();
            if(!price) { alert('Precio requerido'); return; }
            let defs = [];
            let v1 = $('#man-attr1-vals').val().split(',').map(s=>s.trim()).filter(s=>s); if(v1.length) defs.push({name: 'Estilo', values: v1});
            let v2 = $('#man-attr2-vals').val().split(',').map(s=>s.trim()).filter(s=>s); if(v2.length) defs.push({name: 'Color', values: v2});
            let v3 = $('#man-attr3-vals').val().split(',').map(s=>s.trim()).filter(s=>s); if(v3.length) defs.push({name: 'Talla', values: v3});
            if(defs.length === 0) { alert('Llena al menos un atributo'); return; }
            let combos = defs.reduce((a, b) => a.flatMap(x => b.values.map(y => [...x, {name:b.name, value:y}])), [[]]);
            combos.forEach(combo => {
                let attrs = {}; combo.forEach(c => { attrs[c.name] = c.value; });
                pendingVariations.push({ sku: skuBase, price: price, attributes: attrs, image_id: '', shipping: DEFAULT_SHIP });
            });
            renderStagingTable(); $('#man-batch-price').val(''); 
        });

        function renderStagingTable() {
            let tbody = $('#nk-staging-table tbody'); tbody.empty();
            if(pendingVariations.length > 0) { $('#nk-staging-wrapper').show(); } else { $('#nk-staging-wrapper').hide(); }
            pendingVariations.forEach((v, i) => {
                let attrStr = Object.values(v.attributes).join(' / ');
                let refSku = $('#man-sku').val();
                tbody.append(`<tr><td>${refSku}</td><td>${attrStr}</td><td>$${v.price}</td><td><span class="nk-btn-remove" onclick="removeVar(${i})">X</span></td></tr>`);
            });
        }
        window.removeVar = function(i) { pendingVariations.splice(i, 1); renderStagingTable(); };

        $('#nk-finalize-manual').click(function() {
            if(pendingVariations.length === 0) { alert('Sin variantes'); return; }
            let name = $('#man-name').val(); let sku = $('#man-sku').val();
            let cats = []; $('.cat-chk:checked').each(function(){ cats.push($(this).val()); });
            let tags = []; $('.tag-chk:checked').each(function(){ tags.push($(this).val()); });
            let rawAttrs = {};
            pendingVariations.forEach(v => {
                for(let [k, val] of Object.entries(v.attributes)) {
                    if(!rawAttrs[k]) rawAttrs[k] = new Set(); rawAttrs[k].add(val);
                }
            });
            for(let k in rawAttrs) rawAttrs[k] = Array.from(rawAttrs[k]).join(', ');
            let prod = {
                temp_id: Math.random(), type: 'variable', name: name, sku: sku, price: '',
                description: TEMPLATE_DESC, categories: cats.join(', '), tags: tags.join(', '),
                shipping: DEFAULT_SHIP, raw_attributes: rawAttrs, variations: pendingVariations, image_groups: {}, image_id: '', exists_in_db: false
            };
            processSingleProduct(prod); $('#nk-manual-form').hide(); $('#nk-process-btn').show();
        });

        $('#nk-csv-input').change(function(e) {
            if(!e.target.files[0]) return; 
            $('#nk-start-screen').hide(); $('#nk-products-list').html('<p style="text-align:center;">Leyendo...</p>').show(); $('#nk-reset-btn').show();
            Papa.parse(e.target.files[0], { header: false, skipEmptyLines: true, encoding: 'ISO-8859-1', complete: function(r) { processCSVData(r.data); } });
        });

        function processSingleProduct(prod) {
            if (prod.type === 'variable') {
                prod.image_groups = groupVariationsByStyle(prod.variations);
                let prices = prod.variations.map(v => parseFloat(v.price) || 0);
                if(prices.length > 0) {
                    let min = Math.min(...prices); let max = Math.max(...prices);
                    prod.display_price = (min === max) ? min.toFixed(2) : `${min} - ${max}`;
                } else { prod.display_price = '0'; }
                prod.is_variable_price = true;
            } else { 
                prod.display_price = prod.price; 
                prod.is_variable_price = false; 
            }
            parsedProducts.push(prod); 
            checkSkusInDb(parsedProducts); 
        }

        // --- CSV LOGIC FIXED ---
        function findIdx(h, k, e=[]) { 
            for (let i = 0; i < h.length; i++) { 
                let c = (h[i] || '').toLowerCase(); 
                if (k.some(x => c.includes(x)) && !e.some(x => c.includes(x))) return i; 
            } return -1; 
        }

        function processCSVData(rows) {
            parsedProducts = []; 
            
            // MAPEO ESTRICTO DE PRECIO
            let headers = rows[0];
            let map = { 
                type: findIdx(headers, ['tipo','type']), 
                sku: findIdx(headers, ['sku']), 
                name: findIdx(headers, ['nombre','name']), 
                // AQUÍ ESTÁ LA MAGIA: Buscar 'precio normal' o 'regular price' y EXCLUIR 'rebajado' o 'sale'
                price: findIdx(headers, ['precio normal', 'regular price'], ['rebajado', 'sale']), 
                cat: findIdx(headers, ['categor', 'category'], ['catálogo']), 
                desc: findIdx(headers, ['descrip', 'description'], ['corta']), 
                tags: findIdx(headers, ['etiqueta']), 
                weight: findIdx(headers, ['peso']), 
                len: findIdx(headers, ['longitud']), 
                width: findIdx(headers, ['anchura']), 
                height: findIdx(headers, ['altura']) 
            };
            
            let localItems = []; let currentParent=null;
            for(let i=1; i<rows.length; i++) {
                let r=rows[i]; let t=(r[map.type]||'').toLowerCase().trim(); if(!t) continue;
                if(t==='variable'||t==='simple'){
                    let s={weight:(map.weight>-1)?r[map.weight]:'',len:(map.len>-1)?r[map.len]:'',width:(map.width>-1)?r[map.width]:'',height:(map.height>-1)?r[map.height]:''};
                    currentParent={ temp_id:Math.random(), type:t, name:r[map.name]||'Sin Nombre', sku:r[map.sku]||'', price:r[map.price]||'', description:(map.desc>-1)?r[map.desc]:'', categories:(map.cat>-1)?r[map.cat]:'', tags:(map.tags>-1)?r[map.tags]:'', shipping:s, raw_attributes:getAttributes(r,headers), variations:[], image_groups:{}, image_id:'', exists_in_db:false };
                    localItems.push(currentParent);
                } else if(t==='variation'&&currentParent){
                    currentParent.variations.push({ sku:r[map.sku]||'', price:r[map.price]||'0', attributes:getAttributes(r,headers), shipping:{weight:(map.weight>-1)?r[map.weight]:'',len:(map.len>-1)?r[map.len]:'',width:(map.width>-1)?r[map.width]:'',height:(map.height>-1)?r[map.height]:''}, image_id:'' });
                }
            }
            localItems.forEach(p => processSingleProduct(p)); 
        }

        // ... Helpers
        function getAttributes(r,h) { let a={}; for(let i=0;i<h.length;i++){ let x=(h[i]||''); if(x.includes('Nombre del atributo')||(x.includes('Attribute')&&x.includes('name'))){ let n=r[i]; let v=r[i+1]; if(n&&v)a[n]=v; } } return a; }
        function groupVariationsByStyle(vars) { let g={}; vars.forEach(v=>{ let p=[]; for(let [k,val] of Object.entries(v.attributes)){ if(!k.toLowerCase().includes('talla')&&!k.toLowerCase().includes('size')) p.push(val); } let key=p.length>0?p.join(' / '):'General'; if(!g[key])g[key]={label:key,image_id:'',indices:[]}; g[key].indices.push(v); }); return g; }
        function checkSkusInDb(products) { let skus=products.map(p=>p.sku).filter(s=>s); if(skus.length===0){renderUI(); return;} $.post(NK_AJAX_URL, {action:'nakama_check_skus', skus:skus}, function(res){ if(res.success){ let ex=res.data; products.forEach(p=>{ if(ex.includes(p.sku)) p.exists_in_db=true; }); } renderUI(); }); }

        function renderUI() {
            let c=$('#nk-products-list').empty().show(); $('#nk-process-btn').show();
            parsedProducts.forEach((p,i)=>{
                let tL=p.type==='variable'?'Variable':'Simple';
                let imH=''; if(p.type==='variable' && Object.keys(p.image_groups).length > 0){ let th=''; for(let [k,g] of Object.entries(p.image_groups)) th+=`<div class="nk-thumb-item"><div class="nk-thumb-box-sm" onclick="grpImg(${i},'${k}',this)"><span>+</span><img style="display:none"></div><div><div style="font-size:11px; font-weight:bold;">${g.label}</div><div style="font-size:10px;">${g.indices.length} vars</div></div></div>`; imH=`<div class="nk-smart-area"><div class="nk-smart-title">📸 Fotos por Estilo</div><div class="nk-thumbs-grid">${th}</div></div>`; }
                let dup=p.exists_in_db?'<span class="duplicate-label">⚠️ EXISTE</span>':'';
                let s = p.shipping;
                let shipInfo = (s.weight || s.len) ? `📦 ${s.weight}kg | ${s.len}x${s.width}x${s.height}` : `<span style="color:#aaa">Sin datos envío</span>`;

                let html=`<div class="product-card type-${p.type} ${p.exists_in_db?'duplicate-sku':''}">
                    ${dup}
                    <div class="nk-main-info">
                        <div class="nk-thumb-container"><div class="nk-thumb-box-lg" onclick="mainImg(${i},this)"><span>Foto</span><img style="display:none"></div></div>
                        <div class="nk-details">
                            <div style="display:flex; justify-content:space-between;"><div><strong style="color:#2271b1;font-size:11px;text-transform:uppercase;">${tL}</strong> <strong>${p.sku}</strong></div></div>
                            <input type="text" class="nk-row-input" id="nm-${i}" value="${p.name}" style="font-weight:bold;">
                            ${p.is_variable_price?`<input type="text" class="nk-row-input" value="${p.display_price}" disabled style="background:#f0f0f1;" title="Precio calculado de variaciones">`:`<input type="text" class="nk-row-input" id="pr-${i}" value="${p.display_price}">`}
                            <div class="nk-meta-row"><span class="nk-meta-tag">📁 ${p.categories||'Sin Cat'}</span>${p.tags?`<span class="nk-meta-tag">🏷️ ${p.tags}</span>`:''}<span class="nk-meta-tag">${shipInfo}</span></div>
                            <div class="nk-desc-wrapper">
                                <div class="nk-desc-header"><label style="font-size:10px;font-weight:bold;color:#666;">DESCRIPCIÓN</label><button class="nk-btn-template" onclick="tpl(${i})">✨ Usar Plantilla</button></div>
                                <textarea id="desc-${i}" class="nk-desc-area">${p.description}</textarea>
                            </div>
                        </div>
                        <div class="status-col" id="st-${i}">${p.exists_in_db?'<span style="color:red">Omitir</span>':'Pendiente'}</div>
                    </div>${imH}
                </div>`;
                c.append(html);
            });
        }
        
        window.tpl=function(i){ $('#desc-'+i).val(TEMPLATE_DESC); parsedProducts[i].description=TEMPLATE_DESC; };
        window.mainImg=function(i,el){ let f=wp.media({multiple:false}); f.on('select',function(){ let a=f.state().get('selection').first().toJSON(); parsedProducts[i].image_id=a.id; $(el).find('img').attr('src',a.url).show(); $(el).find('span').hide(); }); f.open(); };
        window.grpImg=function(i,k,el){ let f=wp.media({multiple:false}); f.on('select',function(){ let a=f.state().get('selection').first().toJSON(); parsedProducts[i].image_groups[k].indices.forEach(v=>v.image_id=a.id); $(el).find('img').attr('src',a.url).show(); $(el).find('span').hide(); }); f.open(); };
        
        $('#nk-process-btn').click(function(){ let b=$(this); b.prop('disabled',true).text('Procesando...'); $('#nk-progress-bar-container').show(); runQueue(0); });
        function runQueue(i){
            let pct=Math.round((i/parsedProducts.length)*100); $('#nk-progress-bar-fill').css('width',pct+'%');
            if(i>=parsedProducts.length){ alert('Listo'); $('#nk-process-btn').prop('disabled',false).text('Subir'); setTimeout(()=>{ $('#nk-progress-bar-container').hide(); },1000); return; }
            let p=parsedProducts[i];
            if(p.exists_in_db){ $('#st-'+i).html('<span style="color:orange">Omitido</span>'); runQueue(i+1); return; }
            p.name=$('#nm-'+i).val(); p.description=$('#desc-'+i).val(); if(!p.is_variable_price) p.price=$('#pr-'+i).val();
            $('#st-'+i).html('<span style="color:blue">Subiendo...</span>');
            $.post(NK_AJAX_URL, {action:'nakama_create_product', nonce:'<?php echo wp_create_nonce("nk_import_nonce"); ?>', data:p}, function(r){
                if(r.success) $('#st-'+i).html('<span style="color:green">OK</span>'); else $('#st-'+i).html('<span style="color:red">Error</span>'); runQueue(i+1);
            }).fail(function(){ $('#st-'+i).html('<span style="color:red">Red</span>'); runQueue(i+1); });
        }
    });
    </script>
    <?php
}

add_action('wp_ajax_nakama_check_skus', function() { $s=$_POST['skus']; $f=[]; foreach($s as $k) if(wc_get_product_id_by_sku($k)) $f[]=$k; wp_send_json_success($f); });

add_action('wp_ajax_nakama_create_product', function() {
    check_ajax_referer('nk_import_nonce', 'nonce'); if(!current_user_can('manage_woocommerce')) wp_send_json_error();
    $d=$_POST['data']; try {
        $p=($d['type']==='variable')?new WC_Product_Variable():new WC_Product_Simple();
        $p->set_name(sanitize_text_field($d['name'])); $p->set_sku(sanitize_text_field($d['sku'])); $p->set_status('publish');
        if(!empty($d['description'])) $p->set_description(wp_kses_post($d['description']));
        if(!empty($d['image_id'])) $p->set_image_id(absint($d['image_id']));
        if(!empty($d['categories'])) $p->set_category_ids(nakama_get_ids($d['categories'],'product_cat'));
        if(!empty($d['tags'])) $p->set_tag_ids(nakama_get_ids($d['tags'],'product_tag'));
        if(!empty($d['shipping'])){ $s=$d['shipping']; if($s['weight'])$p->set_weight($s['weight']); if($s['len'])$p->set_length($s['len']); if($s['width'])$p->set_width($s['width']); if($s['height'])$p->set_height($s['height']); }
        
        if(!empty($d['raw_attributes'])&&$d['type']==='variable'){ 
            $aa=[]; foreach($d['raw_attributes'] as $n=>$v){ 
                $a=new WC_Product_Attribute(); $a->set_name($n); $a->set_options(array_map('trim',explode(',',$v))); 
                $a->set_position(0); $a->set_visible(true); $a->set_variation(true); $aa[]=$a; 
            } $p->set_attributes($aa); 
        } elseif($d['type']==='simple') $p->set_regular_price($d['price']);
        
        $pid=$p->save();
        
        if($d['type']==='variable'&&!empty($d['variations'])){
            foreach($d['variations'] as $v){
                $vr=new WC_Product_Variation(); $vr->set_parent_id($pid);
                $va=[]; foreach($v['attributes'] as $k=>$val) $va[sanitize_title($k)]=$val; $vr->set_attributes($va);
                $vr->set_regular_price($v['price']); 
                if(!empty($v['image_id']))$vr->set_image_id(absint($v['image_id']));
                if(!empty($v['shipping'])){ $s=$v['shipping']; if($s['weight'])$vr->set_weight($s['weight']); if($s['len'])$vr->set_length($s['len']); if($s['width'])$vr->set_width($s['width']); if($s['height'])$vr->set_height($s['height']); }
                $vr->set_status('publish'); $vr->save();
            }
        } wp_send_json_success(['id'=>$pid]);
    } catch(Exception $e){ wp_send_json_error(['message'=>$e->getMessage()]); }
});
function nakama_get_ids($s,$t){ $i=[]; $n=explode(',',$s); foreach($n as $x){ $x=trim($x); if(!$x)continue; $tm=term_exists($x,$t); if($tm)$i[]=(int)$tm['term_id']; else{ $nw=wp_insert_term($x,$t); if(!is_wp_error($nw))$i[]=(int)$nw['term_id']; } } return $i; }
