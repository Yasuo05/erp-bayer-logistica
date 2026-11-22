# Genera assets/css/erp_marco.css: la barra superior del ERP (de assets/css/style.css) encapsulada bajo .erp-marco
# para Facturación y el Portal Bayer. Uso: python scripts/generar_erp_marco_css.py assets/css/style.css assets/css/erp_marco.css
import re, sys
src = open(sys.argv[1], encoding='utf-8').read()
lines = src.split('\n')
def L(a, b): return '\n'.join(lines[a-1:b])
# :root, barra superior + contenedor + migas, y responsive del menú
def entre(a, b): return src[src.index(a):src.index(b)]
parts = [entre(':root {', '* { box-sizing'), entre('/* ===== TOP NAVIGATION BAR', '/* ===== CONTROLES Y BARRA'), entre('@media (max-width: 1540px)', '@media (max-width: 1080px)')]
tail = src[src.index('/* ===== Marco común (core/erp_marco.php) ===== */'):]
parts.append(tail)
skip = {'.page-headline', '.module-chip', '.page-title', '.page-subtitle', '.bento-card', '.hero-stats-row', '.col-span-8', '.col-span-4', '.col-span-6'}
def pref(sel):
    sel = sel.strip()
    if sel == ':root': return sel
    first = re.split(r'[\s:.>+~\[]', sel.lstrip('.'), maxsplit=1)[0]
    head = '.' + first
    if head in ('.top-nav', '.page-header-bar'):
        return head + '.erp-marco' + sel[len(head):]
    if head == '.main-container':
        return 'body.erp-embebido ' + sel
    return '.erp-marco ' + sel
def blocks(css):
    out, i, n = [], 0, len(css)
    while i < n:
        j = css.find('{', i)
        if j < 0: break
        head = css[i:j].strip(); depth = 1; k = j + 1
        while depth:
            if css[k] == '{': depth += 1
            elif css[k] == '}': depth -= 1
            k += 1
        out.append((head, css[j+1:k-1])); i = k
    return out
def strip_comments(s): return re.sub(r'/\*.*?\*/', '', s, flags=re.S)
def render(css, indent=''):
    res = []
    for head, body in blocks(strip_comments(css)):
        if head.startswith('@media'):
            inner = render(body, indent + '  ')
            if inner.strip(): res.append(f'{indent}{head} {{\n{inner}{indent}}}\n')
            continue
        sels = [s for s in head.split(',') if s.strip()]
        if any(any(s.strip().startswith(k) for k in skip) for s in sels): continue
        decl = '; '.join(d.strip() for d in body.split(';') if d.strip())
        res.append(f"{indent}{', '.join(pref(s) for s in sels)} {{ {decl}; }}\n")
    return ''.join(res)
css = ''.join(render(p) for p in parts)
extra = '''
/* ---- Base de los módulos embebidos: tipografía y fondo del ERP ---- */
body.erp-embebido { margin: 0; font-family: 'Plus Jakarta Sans', Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: var(--bg-canvas); color: var(--text-main); display: flex; flex-direction: column; min-height: 100vh; }
.erp-marco, .erp-marco * { box-sizing: border-box; font-family: 'Plus Jakarta Sans', Inter, -apple-system, "Segoe UI", Roboto, sans-serif; line-height: 1.5; }
.erp-marco a { text-decoration: none; }
.erp-marco .dropdown-menu { float: none; list-style: none; font-size: 13px; z-index: 1000; }
.erp-marco .dropdown-item { width: auto; white-space: nowrap; border: 0; background-color: transparent; }
.erp-marco .dropdown-item:hover, .erp-marco .dropdown-item.item-activo { background: var(--bayer-green-soft); }
.erp-marco .nav-item { margin: 0; border: 0; }
.erp-marco .nav-item::before { content: none; display: none; }
.erp-marco form { margin: 0; }
.erp-marco svg { display: inline-block; vertical-align: middle; }
body.erp-embebido .erp-modulo { padding-top: 4px; }
/* ---- Colores del ERP en los módulos: verde Pucchún como color principal (botones, enlaces, opción activa) ---- */
body.erp-embebido { --primary: #2E8B3D; --primary-hover: #236E30; --primary-soft: #EDF7EE; --primary-strong: #10384F; --focus-ring: 0 0 0 3px rgba(46, 139, 61, 0.2); }
body.erp-embebido .btn-primary { --bs-btn-bg: #2E8B3D; --bs-btn-border-color: #2E8B3D; --bs-btn-hover-bg: #236E30; --bs-btn-hover-border-color: #236E30; --bs-btn-active-bg: #236E30; --bs-btn-active-border-color: #236E30; --bs-btn-disabled-bg: #2E8B3D; --bs-btn-disabled-border-color: #2E8B3D; }
body.erp-embebido .btn-outline-primary { --bs-btn-color: #2E8B3D; --bs-btn-border-color: #2E8B3D; --bs-btn-hover-bg: #2E8B3D; --bs-btn-hover-border-color: #2E8B3D; --bs-btn-active-bg: #236E30; }
'''
cabecera = ('/* GENERADO por scripts/generar_erp_marco_css.py desde assets/css/style.css: barra superior del ERP encapsulada (.erp-marco)' + chr(10)
            + '   para los módulos con diseño propio (Facturación, Portal Bayer). Si cambias la barra en style.css, vuelve a generarlo:' + chr(10)
            + '   python scripts/generar_erp_marco_css.py assets/css/style.css assets/css/erp_marco.css */' + chr(10))
open(sys.argv[2], 'w', encoding='utf-8').write(cabecera + css + extra)
print(len(css))
