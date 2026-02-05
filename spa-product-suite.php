/*
 * Título: Nakama Suite v34 (Dark/Light Toggle) By ImperioDev
 * Descripción: Agrega interruptor de modo claro/oscuro con persistencia de memoria local.
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

        $cats_raw = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
        $cats_formatted = [];
        foreach ($cats_raw as $c) {
            $display_name = $c->name;
            if ($c->parent > 0) {
                $parent = get_term($c->parent, 'product_cat');
                if ($parent && !is_wp_error($parent)) {
                    $display_name .= " ({$parent->name})";
                }
            }
            $cats_formatted[] = ['name' => $c->name, 'display' => $display_name];
        }

        $tags = get_terms(['taxonomy' => 'product_tag', 'hide_empty' => false]);

        wp_localize_script('jquery', 'NK_DATA', ['cats' => $cats_formatted, 'tags' => $tags, 'attrs' => $attributes]);
    }
}

// 2. UI (TOGGLE MODE)
add_action('wp_footer', 'nakama_render_app');
function nakama_render_app() {
    if (!current_user_can('administrator')) return;
    ?>
    <script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/PapaParse/5.4.1/papaparse.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&family=Teko:wght@400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet"/>

    <script>
        // Configuración inicial del tema
        // Verifica si hay preferencia guardada o usa la del sistema
        if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }

        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        primary: "#FF3333",
                        "primary-dark": "#cc0000",
                        // Definimos colores para dark y light
                        "background-dark": "#0A0A0A", 
                        "surface-dark": "#141414", 
                        "input-bg-dark": "#1F1F1F",
                        
                        "background-light": "#F3F4F6",
                        "surface-light": "#FFFFFF",
                        "input-bg-light": "#FFFFFF",
                        
                        "border-dark": "#333333",
                        "border-light": "#E5E7EB"
                    },
                    fontFamily: {
                        display: ["'Teko'", "sans-serif"],
                        body: ["'Inter'", "sans-serif"],
                    }
                },
            },
        };
    </script>

    <style>
        /* --- 🔧 ZONA DE AJUSTES MANUALES --- */
        #nk-modal {
            top: 40px !important; 
            bottom: 20px !important; 
            left: 20px !important;
            right: 20px !important;
            position: fixed !important; 
            border-radius: 12px !important;
        }
        #nk-launcher {
            bottom: 80px !important; 
            left: 17px !important; 
        }
        /* ---------------------------------- */

        #nk-root { font-family: 'Inter', sans-serif; }
        #nk-root h1, #nk-root h2, #nk-root h3, #nk-root .font-display { font-family: 'Teko', sans-serif; }
        
        #nk-modal ::-webkit-scrollbar { width: 8px; }
        
        /* Scrollbar dinámica Dark/Light */
        html.dark #nk-modal ::-webkit-scrollbar-track { background: #0A0A0A; }
        html.dark #nk-modal ::-webkit-scrollbar-thumb { background: #333; }
        
        html:not(.dark) #nk-modal ::-webkit-scrollbar-track { background: #f1f1f1; }
        html:not(.dark) #nk-modal ::-webkit-scrollbar-thumb { background: #ccc; }

        #nk-modal ::-webkit-scrollbar-thumb { border-radius: 4px; }
        #nk-modal ::-webkit-scrollbar-thumb:hover { background: #FF3333; }

        .nk-anim-pop { animation: nkPop 0.3s ease-out forwards; }
        @keyframes nkPop { 0% { transform: scale(0.9); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
        
        /* Inputs adaptables */
        .nk-input-base {
            border-radius: 0.375rem !important; padding: 0.5rem 0.75rem !important; width: 100%;
            transition: all 0.2s; outline: none;
        }
        /* Dark Input */
        html.dark .nk-input-base { background-color: #1F1F1F !important; border: 1px solid #333 !important; color: white !important; }
        html.dark .nk-input-base:focus { border-color: #FF3333 !important; ring: 1px #FF3333 !important; }
        /* Light Input */
        html:not(.dark) .nk-input-base { background-color: #fff !important; border: 1px solid #d1d5db !important; color: #111 !important; }
        html:not(.dark) .nk-input-base:focus { border-color: #FF3333 !important; ring: 1px #FF3333 !important; }
        
        .nk-btn-primary {
            background-color: #FF3333 !important; color: white !important; font-family: 'Teko', sans-serif !important;
            text-transform: uppercase; font-size: 1.25rem !important; padding: 0.5rem 1.5rem !important;
            border-radius: 0.375rem; transition: all 0.2s; border: none; cursor: pointer;
        }
        .nk-btn-primary:hover { background-color: #cc0000 !important; transform: translateY(-1px); }

        .nk-checkbox-list label { display: block; padding: 2px 0; font-size: 0.9rem; cursor: pointer; }
        html.dark .nk-checkbox-list label { color: #ccc; }
        html:not(.dark) .nk-checkbox-list label { color: #333; }
        .nk-checkbox-list label:hover { color: #FF3333 !important; }
        
        /* Datalist Helper */
        .nk-datalist-helper {
            position: absolute; top: 100%; left: 0; width: 100%; max-height: 200px; overflow-y: auto;
            z-index: 50; display: none; border-radius: 0 0 6px 6px; box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            border: 1px solid #FF3333;
        }
        html.dark .nk-datalist-helper { background: #1F1F1F; }
        html:not(.dark) .nk-datalist-helper { background: #fff; }

        .nk-datalist-opt { padding: 8px 12px; cursor: pointer; font-size: 14px; border-bottom: 1px solid; }
        html.dark .nk-datalist-opt { color: #eee; border-color: #333; }
        html:not(.dark) .nk-datalist-opt { color: #333; border-color: #eee; }
        .nk-datalist-opt:hover { background: #FF3333; color: white !important; }

        .media-modal { z-index: 999999 !important; }
        .media-modal-backdrop { z-index: 999998 !important; }
    </style>

    <div id="nk-root">
        <div id="nk-launcher" class="fixed w-16 h-16 bg-primary hover:bg-primary-dark text-white rounded-full flex items-center justify-center cursor-pointer shadow-2xl z-[99990] transition-transform hover:scale-110" title="Abrir Nakama Suite">
            <span class="material-icons-outlined text-3xl">rocket_launch</span>
        </div>

        <div id="nk-modal" style="display:none;" class="dark:bg-black/90 bg-gray-100/90 backdrop-blur-sm z-[99991] overflow-hidden flex flex-col shadow-2xl border dark:border-gray-800 border-gray-300">
            
            <div class="dark:bg-black/80 bg-white/80 p-4 md:p-6 border-b dark:border-gray-800 border-gray-200 flex justify-between items-center shrink-0 backdrop-blur-md">
                <div>
                    <h1 class="text-3xl md:text-4xl dark:text-white text-gray-900 uppercase font-bold m-0 leading-none">Nakama Suite <span class="text-primary">v34</span></h1>
                    <p class="text-gray-500 text-xs md:text-sm m-0">Importador de Productos &bull; ImperioDev Edition</p>
                </div>
                <div class="flex items-center gap-2 md:gap-3">
                    
                    <button id="nk-theme-toggle" class="p-2 rounded-full dark:text-gray-400 text-gray-500 hover:bg-gray-200 dark:hover:bg-gray-800 transition-colors focus:outline-none" title="Cambiar Tema">
                        <span id="nk-theme-icon" class="material-icons-outlined text-xl md:text-2xl">dark_mode</span>
                    </button>
                    <button id="nk-reset-btn" class="hidden px-3 py-1 md:px-4 md:py-2 dark:bg-gray-800 bg-gray-200 dark:text-white text-gray-700 rounded hover:bg-gray-300 dark:hover:bg-gray-700 font-display uppercase tracking-wide text-sm md:text-base">Inicio</button>
                    <button onclick="jQuery('#nk-modal').fadeOut()" class="px-3 py-1 md:px-4 md:py-2 border dark:border-gray-700 border-gray-300 dark:text-gray-300 text-gray-600 rounded hover:text-primary dark:hover:text-white dark:hover:border-white hover:border-primary transition-colors uppercase font-display tracking-wide text-sm md:text-base">Cerrar</button>
                    <button id="nk-process-btn" class="hidden nk-btn-primary shadow-lg shadow-red-900/20 text-sm md:text-lg">🚀 Subir</button>
                </div>
            </div>

            <div id="nk-progress-bar-container" class="hidden w-full h-1 dark:bg-gray-800 bg-gray-300 shrink-0">
                <div id="nk-progress-bar-fill" class="h-full bg-primary transition-all duration-300 w-0"></div>
            </div>

            <div class="flex-1 overflow-y-auto p-4 md:p-8">
                
                <div id="nk-start-screen" class="flex flex-col md:flex-row gap-6 justify-center py-10 h-full items-center">
                    <div class="group dark:bg-input-bg-dark bg-white border dark:border-gray-700 border-gray-200 hover:border-primary p-10 rounded-xl cursor-pointer transition-all hover:-translate-y-2 text-center w-full md:w-80 shadow-lg" onclick="document.getElementById('nk-csv-input').click()">
                        <span class="material-icons-outlined text-6xl text-gray-400 group-hover:text-primary mb-4 transition-colors">folder_open</span>
                        <h3 class="text-2xl dark:text-white text-gray-900 uppercase font-bold">Importar CSV</h3>
                        <p class="text-gray-500 text-sm">Carga masiva desde archivo</p>
                        <input type="file" id="nk-csv-input" accept=".csv" class="hidden" />
                    </div>
                    
                    <div id="nk-btn-manual-mode" class="group dark:bg-input-bg-dark bg-white border dark:border-gray-700 border-gray-200 hover:border-primary p-10 rounded-xl cursor-pointer transition-all hover:-translate-y-2 text-center w-full md:w-80 shadow-lg">
                        <span class="material-icons-outlined text-6xl text-gray-400 group-hover:text-primary mb-4 transition-colors">edit_note</span>
                        <h3 class="text-2xl dark:text-white text-gray-900 uppercase font-bold">Crear Manual</h3>
                        <p class="text-gray-500 text-sm">Constructor visual de productos</p>
                    </div>
                </div>

                <div id="nk-manual-form" class="hidden max-w-5xl mx-auto pb-10">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-3xl dark:text-white text-gray-900 uppercase m-0">Configuración</h2>
                        <button id="nk-wipe-form" class="text-red-500 hover:text-red-600 text-sm flex items-center gap-1 uppercase font-bold tracking-wide transition-colors"><span class="material-icons-outlined text-base">delete_sweep</span> Limpiar Todo</button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label class="block text-gray-500 dark:text-gray-400 text-xs uppercase font-bold mb-2">Nombre del Producto</label>
                            <input type="text" id="man-name" class="nk-input-base" placeholder="Ej: Camiseta Luffy Gear 5">
                        </div>
                        <div>
                            <label class="block text-gray-500 dark:text-gray-400 text-xs uppercase font-bold mb-2">SKU Base (Padre)</label>
                            <input type="text" id="man-sku" class="nk-input-base" placeholder="Ej: OP-LUFFY-G5">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                        <div>
                            <label class="block text-gray-500 dark:text-gray-400 text-xs uppercase font-bold mb-2">Categorías</label>
                            <input type="text" class="nk-input-base mb-2" placeholder="Filtrar categorías..." onkeyup="filterList(this, '#man-cats-list')">
                            <div id="man-cats-list" class="nk-checkbox-list h-32 overflow-y-auto dark:bg-input-bg-dark bg-white border dark:border-gray-700 border-gray-300 rounded p-2 custom-scroll"></div>
                        </div>
                        <div>
                            <label class="block text-gray-500 dark:text-gray-400 text-xs uppercase font-bold mb-2">Etiquetas</label>
                            <input type="text" class="nk-input-base mb-2" placeholder="Filtrar etiquetas..." onkeyup="filterList(this, '#man-tags-list')">
                            <div id="man-tags-list" class="nk-checkbox-list h-32 overflow-y-auto dark:bg-input-bg-dark bg-white border dark:border-gray-700 border-gray-300 rounded p-2 custom-scroll"></div>
                        </div>
                    </div>

                    <div class="dark:bg-black/30 bg-gray-50 border border-dashed dark:border-gray-700 border-gray-300 rounded-xl p-6 relative">
                        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 pb-4 border-b dark:border-gray-800 border-gray-200 gap-4">
                            <span class="text-primary font-display text-xl uppercase tracking-wider flex items-center gap-2"><span class="material-icons-outlined">tune</span> Generador de Variantes</span>
                            
                            <div class="flex gap-2">
                                <button class="px-3 py-2 dark:bg-gray-800 bg-gray-200 hover:bg-primary dark:hover:bg-primary text-gray-700 dark:text-white hover:text-white text-xs rounded uppercase font-bold transition-colors flex items-center gap-1" onclick="applyTemplate_SemitonoNegro()"><span class="material-icons-outlined text-sm">flash_on</span> Plantilla Negra</button>
                                <button id="nk-clear-inputs" class="px-3 py-2 border dark:border-gray-600 border-gray-300 hover:border-red-500 text-gray-500 hover:text-red-500 text-xs rounded uppercase font-bold transition-colors">Limpiar Campos</button>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div class="flex flex-col md:flex-row md:items-center gap-2 md:gap-4">
                                <div class="w-full md:w-24 shrink-0"><span class="text-gray-500 text-xs uppercase font-bold">Color</span></div>
                                <div class="relative flex-1">
                                    <input type="text" id="man-attr1-vals" class="nk-input-base" placeholder="Ej: Negro, Blanco" autocomplete="off">
                                    <div class="nk-datalist-helper" id="list-color"></div>
                                </div>
                            </div>
                            
                            <div class="flex flex-col md:flex-row md:items-center gap-2 md:gap-4">
                                <div class="w-full md:w-24 shrink-0"><span class="text-gray-500 text-xs uppercase font-bold">Estilo</span></div>
                                <div class="relative flex-1">
                                    <input type="text" id="man-attr2-vals" class="nk-input-base" placeholder="Ej: Oversize, T-shirt" autocomplete="off">
                                    <div class="nk-datalist-helper" id="list-estilo"></div>
                                </div>
                            </div>

                            <div class="flex flex-col md:flex-row md:items-center gap-2 md:gap-4">
                                <div class="w-full md:w-24 shrink-0 flex justify-between md:block">
                                    <span class="text-gray-500 text-xs uppercase font-bold">Talla</span>
                                    <div class="md:hidden flex gap-1">
                                        <button type="button" class="bg-gray-200 dark:bg-gray-700 text-[10px] px-2 py-0.5 rounded dark:text-white" onclick="fillSizes('normal')">S-XL</button>
                                        <button type="button" class="bg-gray-200 dark:bg-gray-700 text-[10px] px-2 py-0.5 rounded dark:text-white" onclick="fillSizes('full')">S-3XL</button>
                                    </div>
                                </div>
                                <div class="relative flex-1">
                                    <div class="hidden md:flex absolute right-2 top-1.5 gap-1 z-10">
                                        <button type="button" class="dark:bg-gray-800 bg-gray-200 hover:bg-primary dark:hover:bg-primary border dark:border-gray-600 border-gray-300 text-[10px] px-2 py-0.5 rounded dark:text-white text-gray-700 hover:text-white uppercase transition-colors" onclick="fillSizes('normal')">S-XL</button>
                                        <button type="button" class="dark:bg-gray-800 bg-gray-200 hover:bg-primary dark:hover:bg-primary border dark:border-gray-600 border-gray-300 text-[10px] px-2 py-0.5 rounded dark:text-white text-gray-700 hover:text-white uppercase transition-colors" onclick="fillSizes('full')">S-3XL</button>
                                    </div>
                                    <input type="text" id="man-attr3-vals" class="nk-input-base" placeholder="Ej: S, M, L" autocomplete="off">
                                    <div class="nk-datalist-helper" id="list-talla"></div>
                                </div>
                            </div>

                            <div class="flex flex-col md:flex-row gap-4 items-end mt-6 pt-6 border-t dark:border-gray-800 border-gray-200">
                                <div class="w-full md:w-1/3">
                                    <label class="text-xs uppercase text-gray-500 font-bold mb-1 block">Precio del Lote ($)</label>
                                    <input type="number" id="man-batch-price" class="nk-input-base text-xl font-bold text-primary" placeholder="0.00">
                                </div>
                                <div class="w-full md:flex-1">
                                    <button id="nk-add-batch" class="w-full py-3 dark:bg-gray-800 bg-gray-200 hover:bg-black hover:text-white dark:hover:bg-white dark:hover:text-black dark:text-white text-gray-800 rounded font-display uppercase text-xl transition-colors border dark:border-gray-600 border-gray-300 shadow-md">➕ Generar Variantes</button>
                                </div>
                            </div>
                        </div>

                        <div id="nk-staging-wrapper" class="mt-8 hidden">
                            <div class="flex justify-between items-center mb-3">
                                <h4 class="dark:text-white text-gray-900 font-bold text-sm uppercase flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-green-500"></span> Previsualización</h4>
                                <button id="nk-clear-table" class="text-red-500 text-xs uppercase font-bold hover:text-red-600 transition-colors">Borrar Todo</button>
                            </div>
                            
                            <div class="dark:bg-black bg-white rounded border dark:border-gray-800 border-gray-300 overflow-hidden max-h-60 overflow-y-auto custom-scroll">
                                <div class="grid grid-cols-12 gap-2 dark:bg-gray-900 bg-gray-100 text-gray-500 uppercase text-xs font-bold sticky top-0 z-10 border-b dark:border-gray-800 border-gray-300">
                                    <div class="col-span-3 p-3">SKU Ref</div>
                                    <div class="col-span-5 p-3">Combinación</div>
                                    <div class="col-span-2 p-3">Precio</div>
                                    <div class="col-span-2 p-3 text-right">Borrar</div>
                                </div>
                                
                                <div id="nk-staging-body" class="divide-y dark:divide-gray-800 divide-gray-200"></div>
                            </div>
                        </div>
                    </div>

                    <button id="nk-finalize-manual" class="nk-btn-primary w-full mt-8 py-4 text-2xl shadow-xl shadow-red-900/30 hover:scale-[1.01] active:scale-[0.99]">✅ Confirmar y Crear Tarjeta</button>
                </div>

                <div id="nk-products-list" class="hidden grid gap-6 pb-20 max-w-5xl mx-auto"></div>

            </div>
        </div>
    </div>

    <script>
    var pendingVariations = [];
    const DEFAULT_SHIP = { weight: '0.25', len: '30', width: '25', height: '2' };
    const TEMPLATE_DESC = `✨ Fabricado con pasión en Nakama Bordados ✨\n\nCada uno de nuestros productos es elaborado cuidadosamente en nuestro taller, combinando bordado y estampado de alta calidad para ofrecerte piezas únicas, duraderas y llenas de estilo. Ya sea una prenda bordada o estampada, cuidamos cada detalle para garantizar un acabado profesional y resistente.`;

    // --- DARK MODE LOGIC ---
    const themeToggleBtn = document.getElementById('nk-theme-toggle');
    const themeIcon = document.getElementById('nk-theme-icon');

    function updateThemeIcon() {
        if (document.documentElement.classList.contains('dark')) {
            themeIcon.innerText = 'light_mode'; // Icono de Sol para ir a Light
        } else {
            themeIcon.innerText = 'dark_mode'; // Icono de Luna para ir a Dark
        }
    }

    themeToggleBtn.addEventListener('click', function() {
        if (document.documentElement.classList.contains('dark')) {
            document.documentElement.classList.remove('dark');
            localStorage.setItem('color-theme', 'light');
        } else {
            document.documentElement.classList.add('dark');
            localStorage.setItem('color-theme', 'dark');
        }
        updateThemeIcon();
    });
    updateThemeIcon(); // Init
    // -----------------------

    function filterList(input, listId) {
        var filter = input.value.toUpperCase();
        var labels = document.querySelectorAll(listId + ' label');
        labels.forEach(l => {
            var txt = l.textContent || l.innerText;
            l.style.display = txt.toUpperCase().indexOf(filter) > -1 ? "" : "none";
        });
    }

    // Funciones globales
    window.fillSizes = function(type) {
        let vals = (type === 'normal') ? 'S, M, L, XL' : 'S, M, L, XL, 2XL, 3XL';
        jQuery('#man-attr3-vals').val(vals);
    };

    window.applyTemplate_SemitonoNegro = function() {
        if(!confirm('¿Aplicar plantilla Semitono (Negro)?')) return;
        pendingVariations = []; 
        let skuBase = jQuery('#man-sku').val();
        const CONFIG = [
            { st: 'T-shirt',   sz: ['S', 'M', 'L', 'XL'], pr: '299' }, { st: 'T-shirt',   sz: ['2XL'], pr: '330' },
            { st: 'Oversize',  sz: ['S', 'M', 'L', 'XL'], pr: '399' }, { st: 'Oversize',  sz: ['2XL', '3XL'], pr: '439' },
            { st: 'Acid Wash', sz: ['S', 'M', 'L', 'XL'], pr: '319' }, { st: 'Acid Wash', sz: ['2XL'], pr: '339' },
            { st: 'Tank Top',  sz: ['S', 'M', 'L', 'XL'], pr: '289' }, { st: 'Tank Top',  sz: ['2XL', '3XL'], pr: '309' },
            { st: 'Sudadera',  sz: ['S', 'M', 'L', 'XL'], pr: '399' }, { st: 'Sudadera',  sz: ['2XL'], pr: '439' }
        ];
        CONFIG.forEach(grp => {
            grp.sz.forEach(size => {
                let attrs = { 'Color': 'Negro', 'Estilo': grp.st, 'Talla': size };
                pendingVariations.push({ sku: skuBase, price: grp.pr, attributes: attrs, image_id: '', shipping: DEFAULT_SHIP });
            });
        });
        jQuery('#man-attr1-vals, #man-attr2-vals, #man-attr3-vals, #man-batch-price').val('');
        renderStagingTable();
    };

    // --- RENDERIZADO GRID (FIX V33) ---
    window.renderStagingTable = function() {
        let container = jQuery('#nk-staging-body'); container.empty();
        if(pendingVariations.length > 0) { jQuery('#nk-staging-wrapper').show(); } else { jQuery('#nk-staging-wrapper').hide(); }
        
        pendingVariations.forEach((v, i) => {
            let attrStr = Object.values(v.attributes).join(' <span class="text-gray-400">/</span> ');
            let refSku = jQuery('#man-sku').val();
            
            let rowHtml = `
                <div class="grid grid-cols-12 gap-2 p-3 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors items-center text-sm">
                    <div class="col-span-3 font-mono text-gray-500 dark:text-gray-400 break-all text-xs">${refSku}</div>
                    <div class="col-span-5 font-bold text-gray-800 dark:text-white">${attrStr}</div>
                    <div class="col-span-2 text-primary font-bold">$${v.price}</div>
                    <div class="col-span-2 text-right">
                        <span class="text-red-500 cursor-pointer font-bold hover:text-red-400 text-lg leading-none" onclick="removeVar(${i})">&times;</span>
                    </div>
                </div>
            `;
            container.append(rowHtml);
        });
    };

    jQuery(document).ready(function($) {
        const NK_AJAX_URL = '<?php echo admin_url('admin-ajax.php'); ?>';
        let parsedProducts = []; 

        function init() {
            if(typeof NK_DATA !== 'undefined') {
                $('#man-cats-list').html(NK_DATA.cats.map(c => `<label><input type="checkbox" value="${c.name}" class="cat-chk accent-primary mr-2"> ${c.display}</label>`).join(''));
                $('#man-tags-list').html(NK_DATA.tags.map(t => `<label><input type="checkbox" value="${t.name}" class="tag-chk accent-primary mr-2"> ${t.name}</label>`).join(''));
                bindSuggestions('Color', '#list-color', '#man-attr1-vals');
                bindSuggestions('Estilo', '#list-estilo', '#man-attr2-vals');
                bindSuggestions('Talla', '#list-talla', '#man-attr3-vals');
            }
        }
        
        function bindSuggestions(keyword, listId, inputId) {
            let found = NK_DATA.attrs.find(a => a.label.toLowerCase().includes(keyword.toLowerCase()));
            if(!found && keyword === 'Talla') found = NK_DATA.attrs.find(a => a.slug.includes('size'));
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

        $('#nk-launcher').click(function() { $('#nk-modal').fadeIn().addClass('flex'); });
        $('#nk-btn-manual-mode').click(function() { $('#nk-start-screen').hide(); $('#nk-manual-form').fadeIn(); $('#nk-reset-btn').show(); });
        
        $('#nk-wipe-form').click(function() {
            if(confirm('¿LIMPIEZA TOTAL?')) {
                $('#man-name, #man-sku').val('');
                $('.cat-chk, .tag-chk').prop('checked', false);
                $('#man-attr1-vals, #man-attr2-vals, #man-attr3-vals, #man-batch-price').val('');
                pendingVariations = []; renderStagingTable();
            }
        });

        $('#nk-clear-inputs').click(function() { $('#man-attr1-vals, #man-attr2-vals, #man-attr3-vals, #man-batch-price').val(''); });
        $('#nk-clear-table').click(function() { if(confirm('¿Borrar variantes?')) { pendingVariations = []; renderStagingTable(); } });
        $('#nk-reset-btn').click(function() { if(confirm('¿Reiniciar?')) { parsedProducts = []; pendingVariations = []; $('#nk-products-list').empty().hide(); $('#nk-manual-form').hide(); $('#nk-start-screen').fadeIn(); $('#nk-process-btn').hide(); $('#nk-reset-btn').hide(); $('#nk-csv-input').val(''); renderStagingTable(); } });

        $('#nk-add-batch').click(function() {
            let price = $('#man-batch-price').val(); let skuBase = $('#man-sku').val();
            if(!price) { alert('Precio requerido'); return; }
            let defs = [];
            let v1 = $('#man-attr1-vals').val().split(',').map(s=>s.trim()).filter(s=>s); if(v1.length) defs.push({name: 'Color', values: v1});
            let v2 = $('#man-attr2-vals').val().split(',').map(s=>s.trim()).filter(s=>s); if(v2.length) defs.push({name: 'Estilo', values: v2});
            let v3 = $('#man-attr3-vals').val().split(',').map(s=>s.trim()).filter(s=>s); if(v3.length) defs.push({name: 'Talla', values: v3});
            
            if(defs.length === 0) { alert('Llena al menos un atributo'); return; }
            let combos = defs.reduce((a, b) => a.flatMap(x => b.values.map(y => [...x, {name:b.name, value:y}])), [[]]);
            combos.forEach(combo => {
                let attrs = {}; combo.forEach(c => { attrs[c.name] = c.value; });
                pendingVariations.push({ sku: skuBase, price: price, attributes: attrs, image_id: '', shipping: DEFAULT_SHIP });
            });
            renderStagingTable(); $('#man-batch-price').val(''); 
        });

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
            processSingleProduct(prod); $('#nk-manual-form').hide(); $('#nk-process-btn').removeClass('hidden').show();
        });

        $('#nk-csv-input').change(function(e) {
            if(!e.target.files[0]) return; 
            $('#nk-start-screen').hide(); $('#nk-products-list').html('<p class="dark:text-white text-gray-800 text-center mt-10">Leyendo...</p>').show(); $('#nk-reset-btn').show();
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

        // CSV Logic
        function findIdx(h, k, e=[]) { 
            for (let i = 0; i < h.length; i++) { 
                let c = (h[i] || '').toLowerCase(); 
                if (k.some(x => c.includes(x)) && !e.some(x => c.includes(x))) return i; 
            } return -1; 
        }

        function processCSVData(rows) {
            parsedProducts = []; 
            let headers = rows[0];
            let map = { 
                type: findIdx(headers, ['tipo','type']), sku: findIdx(headers, ['sku']), name: findIdx(headers, ['nombre','name']), 
                price: findIdx(headers, ['precio normal', 'regular price'], ['rebajado', 'sale']), 
                cat: findIdx(headers, ['categor', 'category'], ['catálogo']), desc: findIdx(headers, ['descrip', 'description'], ['corta']), 
                tags: findIdx(headers, ['etiqueta']), weight: findIdx(headers, ['peso']), len: findIdx(headers, ['longitud']), 
                width: findIdx(headers, ['anchura']), height: findIdx(headers, ['altura']) 
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

        function getAttributes(r,h) { let a={}; for(let i=0;i<h.length;i++){ let x=(h[i]||''); if(x.includes('Nombre del atributo')||(x.includes('Attribute')&&x.includes('name'))){ let n=r[i]; let v=r[i+1]; if(n&&v)a[n]=v; } } return a; }
        function groupVariationsByStyle(vars) { let g={}; vars.forEach(v=>{ let p=[]; for(let [k,val] of Object.entries(v.attributes)){ if(!k.toLowerCase().includes('talla')&&!k.toLowerCase().includes('size')) p.push(val); } let key=p.length>0?p.join(' / '):'General'; if(!g[key])g[key]={label:key,image_id:'',indices:[]}; g[key].indices.push(v); }); return g; }
        function checkSkusInDb(products) { let skus=products.map(p=>p.sku).filter(s=>s); if(skus.length===0){renderUI(); return;} $.post(NK_AJAX_URL, {action:'nakama_check_skus', skus:skus}, function(res){ if(res.success){ let ex=res.data; products.forEach(p=>{ if(ex.includes(p.sku)) p.exists_in_db=true; }); } renderUI(); }); }

        // Render Cards (Dark Mode)
        function renderUI() {
            let c=$('#nk-products-list').empty().show(); $('#nk-process-btn').removeClass('hidden');
            parsedProducts.forEach((p,i)=>{
                let tL=p.type==='variable'?'Variable':'Simple';
                let imH=''; if(p.type==='variable' && Object.keys(p.image_groups).length > 0){ let th=''; for(let [k,g] of Object.entries(p.image_groups)) th+=`<div class="dark:bg-gray-900 bg-gray-100 border dark:border-gray-700 border-gray-300 rounded p-2 flex items-center gap-3"><div class="w-10 h-10 dark:bg-black bg-white rounded cursor-pointer relative overflow-hidden border dark:border-gray-600 border-gray-300 hover:border-primary" onclick="grpImg(${i},'${k}',this)"><span class="absolute inset-0 flex items-center justify-center text-gray-500 hover:text-primary font-bold">+</span><img style="display:none" class="w-full h-full object-cover"></div><div><div class="text-xs font-bold dark:text-white text-gray-800">${g.label}</div><div class="text-[10px] text-gray-500">${g.indices.length} vars</div></div></div>`; imH=`<div class="mt-4 dark:bg-black/50 bg-gray-50 p-4 rounded border border-dashed dark:border-gray-700 border-gray-300"><div class="text-xs font-bold text-gray-400 uppercase mb-2">📸 Fotos por Estilo</div><div class="flex flex-wrap gap-2">${th}</div></div>`; }
                let dup=p.exists_in_db?'<span class="bg-red-900 text-red-200 text-xs px-2 py-1 rounded font-bold uppercase">⚠️ Ya existe</span>':'';
                let s = p.shipping;
                let shipInfo = (s.weight || s.len) ? `📦 ${s.weight}kg | ${s.len}x${s.width}x${s.height}` : `<span class="text-gray-500">Sin envío</span>`;

                let html=`<div class="dark:bg-surface-dark bg-white border ${p.exists_in_db ? 'border-red-900' : 'dark:border-gray-800 border-gray-200'} rounded-xl p-6 shadow-lg flex flex-col relative overflow-hidden transition-all">
                    ${p.exists_in_db ? '<div class="absolute top-0 right-0 bg-red-600 text-white text-xs font-bold px-3 py-1 uppercase rounded-bl-lg">Duplicado</div>' : ''}
                    <div class="flex gap-6 items-start border-b dark:border-gray-800 border-gray-200 pb-4 mb-4">
                        <div class="w-24 h-24 dark:bg-black bg-gray-100 rounded-lg border dark:border-gray-700 border-gray-300 flex items-center justify-center cursor-pointer hover:border-primary transition-colors overflow-hidden relative" onclick="mainImg(${i},this)">
                            <span class="text-gray-500 text-xs uppercase font-bold">Foto</span>
                            <img style="display:none" class="absolute inset-0 w-full h-full object-cover">
                        </div>
                        <div class="flex-1">
                            <div class="flex justify-between mb-2">
                                <div><span class="text-primary font-display uppercase text-lg">${tL}</span> <span class="text-gray-400 font-mono text-sm ml-2">${p.sku}</span></div>
                            </div>
                            <input type="text" class="nk-input-base text-lg font-bold mb-2" id="nm-${i}" value="${p.name}">
                            ${p.is_variable_price?`<input type="text" class="nk-input-base text-gray-500 cursor-not-allowed" value="${p.display_price}" disabled>`:`<input type="text" class="nk-input-base text-primary font-bold" id="pr-${i}" value="${p.display_price}">`}
                            
                            <div class="flex gap-2 mt-3 flex-wrap">
                                <span class="dark:bg-gray-800 bg-gray-200 dark:text-gray-300 text-gray-700 text-xs px-2 py-1 rounded border dark:border-gray-700 border-gray-300">📁 ${p.categories||'Sin Cat'}</span>
                                ${p.tags?`<span class="dark:bg-gray-800 bg-gray-200 dark:text-gray-300 text-gray-700 text-xs px-2 py-1 rounded border dark:border-gray-700 border-gray-300">🏷️ ${p.tags}</span>`:''}
                                <span class="dark:bg-gray-800 bg-gray-200 dark:text-gray-300 text-gray-700 text-xs px-2 py-1 rounded border dark:border-gray-700 border-gray-300">${shipInfo}</span>
                            </div>
                        </div>
                        <div class="w-24 text-right font-bold text-sm text-gray-500" id="st-${i}">${p.exists_in_db?'<span class="text-red-500">Omitir</span>':'Pendiente'}</div>
                    </div>
                    
                    <div class="dark:bg-gray-900/50 bg-gray-50 p-4 rounded border dark:border-gray-800 border-gray-200">
                        <div class="flex justify-between items-center mb-2">
                            <label class="text-xs font-bold text-gray-500 uppercase">Descripción</label>
                            <button class="text-primary text-xs uppercase font-bold hover:text-red-600" onclick="tpl(${i})">✨ Usar Plantilla</button>
                        </div>
                        <textarea id="desc-${i}" class="nk-input-base h-20 text-sm">${p.description}</textarea>
                    </div>
                    ${imH}
                </div>`;
                c.append(html);
            });
        }
        
        window.tpl=function(i){ $('#desc-'+i).val(TEMPLATE_DESC); parsedProducts[i].description=TEMPLATE_DESC; };
        window.mainImg=function(i,el){ let f=wp.media({multiple:false}); f.on('select',function(){ let a=f.state().get('selection').first().toJSON(); parsedProducts[i].image_id=a.id; $(el).find('img').attr('src',a.url).show(); $(el).find('span').hide(); }); f.open(); };
        window.grpImg=function(i,k,el){ let f=wp.media({multiple:false}); f.on('select',function(){ let a=f.state().get('selection').first().toJSON(); parsedProducts[i].image_groups[k].indices.forEach(v=>v.image_id=a.id); $(el).find('img').attr('src',a.url).show(); $(el).find('span').hide(); }); f.open(); };
        
        $('#nk-process-btn').click(function(){ 
            let b=$(this); b.prop('disabled',true).text('Procesando...'); 
            $('#nk-progress-bar-container').removeClass('hidden'); 
            runQueue(0); 
        });

        function runQueue(i){
            let pct=Math.round((i/parsedProducts.length)*100); 
            $('#nk-progress-bar-fill').css('width',pct+'%');
            
            if(i>=parsedProducts.length){ 
                alert('¡PROCESO COMPLETADO NAKAMA! 🏴‍☠️'); 
                $('#nk-process-btn').prop('disabled',false).text('🚀 Subir a WooCommerce'); 
                setTimeout(()=>{ $('#nk-progress-bar-container').addClass('hidden'); },1000); 
                return; 
            }
            
            let p=parsedProducts[i];
            if(p.exists_in_db){ $('#st-'+i).html('<span class="text-orange-500">Omitido</span>'); runQueue(i+1); return; }
            
            p.name=$('#nm-'+i).val(); p.description=$('#desc-'+i).val(); if(!p.is_variable_price) p.price=$('#pr-'+i).val();
            $('#st-'+i).html('<span class="text-blue-400 animate-pulse">Subiendo...</span>');
            
            $.post(NK_AJAX_URL, {action:'nakama_create_product', nonce:'<?php echo wp_create_nonce("nk_import_nonce"); ?>', data:p}, function(r){
                if(r.success) $('#st-'+i).html('<span class="text-green-500">OK</span>'); else $('#st-'+i).html('<span class="text-red-500">Error</span>'); runQueue(i+1);
            }).fail(function(){ $('#st-'+i).html('<span class="text-red-500">Red</span>'); runQueue(i+1); });
        }
    });
    </script>
    <?php
}

add_action('wp_ajax_nakama_check_skus', function() { $s=$_POST['skus']; $f=[]; foreach($s as $k) if(wc_get_product_id_by_sku($k)) $f[]=$k; wp_send_json_success($f); });

// 3. BACKEND PROCESSING (GALLERY DEDUPLICATION FIX)
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
        
        // Atributos
        if(!empty($d['raw_attributes'])&&$d['type']==='variable'){ 
            $aa=[]; 
            $attribute_order = ['pa_color' => 1, 'pa_estilo' => 2, 'pa_size' => 3];
            foreach($d['raw_attributes'] as $n=>$v){ 
                $a=new WC_Product_Attribute(); 
                $clean_name = strtolower(trim($n));
                $taxonomy_slug = '';
                if(strpos($clean_name, 'color') !== false) $taxonomy_slug = 'pa_color';
                elseif(strpos($clean_name, 'estilo') !== false) $taxonomy_slug = 'pa_estilo';
                elseif(strpos($clean_name, 'talla') !== false || strpos($clean_name, 'size') !== false) $taxonomy_slug = 'pa_size';
                else $taxonomy_slug = wc_attribute_taxonomy_name($n); 
                if(taxonomy_exists($taxonomy_slug)) {
                    $a->set_name($taxonomy_slug);
                    $attr_base_name = str_replace('pa_', '', $taxonomy_slug);
                    $attr_id = wc_attribute_taxonomy_id_by_name($attr_base_name);
                    $a->set_id($attr_id); 
                    $a->set_options(nakama_get_ids($v, $taxonomy_slug)); 
                    $a->set_visible(($taxonomy_slug === 'pa_color'));
                } else {
                    $a->set_name($n); $a->set_options(array_map('trim',explode(',',$v))); 
                    $a->set_visible(true); $a->set_id(0);
                }
                $a->set_position(0); $a->set_variation(true); 
                $aa[]=$a; 
            } 
            usort($aa, function($a, $b) use ($attribute_order) {
                return ($attribute_order[$a->get_name()] ?? 99) - ($attribute_order[$b->get_name()] ?? 99);
            });
            $p->set_attributes($aa); 
        } elseif($d['type']==='simple') $p->set_regular_price($d['price']);
        
        $pid=$p->save();
        
        // Variables
        $min_p = null; $max_p = null; 
        $gallery_ids = []; 

        if($d['type']==='variable'&&!empty($d['variations'])){
            foreach($d['variations'] as $v){
                $vr=new WC_Product_Variation(); $vr->set_parent_id($pid);
                $va=[]; 
                foreach($v['attributes'] as $k=>$val) {
                    $clean_k = strtolower(trim($k));
                    $tax_key = '';
                    if(strpos($clean_k, 'color') !== false) $tax_key = 'pa_color';
                    elseif(strpos($clean_k, 'estilo') !== false) $tax_key = 'pa_estilo';
                    elseif(strpos($clean_k, 'talla') !== false || strpos($clean_k, 'size') !== false) $tax_key = 'pa_size';
                    else $tax_key = wc_attribute_taxonomy_name($k);
                    if(taxonomy_exists($tax_key)) {
                        $term = get_term_by('name', $val, $tax_key);
                        if($term && !is_wp_error($term)) { $va[$tax_key] = $term->slug; } else { $va[$tax_key] = sanitize_title($val); }
                    } else {
                        $va[$tax_key] = $val; 
                    }
                }
                $vr->set_attributes($va);
                $price_val = floatval($v['price']);
                $vr->set_regular_price($price_val); 
                
                if(is_null($min_p) || $price_val < $min_p) $min_p = $price_val;
                if(is_null($max_p) || $price_val > $max_p) $max_p = $price_val;

                if(!empty($v['image_id'])) {
                    $img_id = absint($v['image_id']);
                    $vr->set_image_id($img_id);
                    $gallery_ids[] = $img_id; 
                }

                if(!empty($v['shipping'])){ $s=$v['shipping']; if($s['weight'])$vr->set_weight($s['weight']); if($s['len'])$vr->set_length($s['len']); if($s['width'])$vr->set_width($s['width']); if($s['height'])$vr->set_height($s['height']); }
                $vr->set_status('publish'); $vr->save();
            }

            // --- GALLERY FIX: ELIMINAR DUPLICIDAD IMAGEN PRINCIPAL ---
            if(!empty($gallery_ids)) {
                $unique_gallery = array_unique($gallery_ids);
                $main_img_id = $p->get_image_id(); // Obtener ID de la destacada
                
                // Si existe imagen principal, quitarla del array de galería
                if($main_img_id) {
                    $unique_gallery = array_diff($unique_gallery, array($main_img_id));
                }
                
                $pf = wc_get_product($pid);
                $pf->set_gallery_image_ids($unique_gallery);
                $pf->save();
            }

            // --- HARD FIX PRECIOS ---
            if (!is_null($min_p)) {
                update_post_meta($pid, '_price', $min_p);
                update_post_meta($pid, '_min_variation_price', $min_p);
                update_post_meta($pid, '_max_variation_price', $max_p);
                update_post_meta($pid, '_min_variation_regular_price', $min_p);
                update_post_meta($pid, '_max_variation_regular_price', $max_p);
                $pf = wc_get_product($pid);
                $pf->save(); 
            }

        } wp_send_json_success(['id'=>$pid]);
    } catch(Exception $e){ wp_send_json_error(['message'=>$e->getMessage()]); }
});

function nakama_get_ids($s,$t){ $i=[]; $n=explode(',',$s); foreach($n as $x){ $x=trim($x); if(!$x)continue; $tm=term_exists($x,$t); if($tm)$i[]=(int)$tm['term_id']; else{ $nw=wp_insert_term($x,$t); if(!is_wp_error($nw))$i[]=(int)$nw['term_id']; } } return $i; }
