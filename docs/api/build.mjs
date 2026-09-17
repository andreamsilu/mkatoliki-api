import { execFileSync } from 'node:child_process';
import { readFileSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const directory = dirname(fileURLToPath(import.meta.url));
const root = resolve(directory, '../..');
const spec = JSON.parse(readFileSync(resolve(directory, 'openapi.json'), 'utf8'));
const markdown = readFileSync(resolve(directory, 'consumer-guide.md'), 'utf8');
const escape = (value) => String(value).replace(/[&<>"']/g, (character) => ({
  '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
}[character]));
const slug = (value) => value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
const resolveReference = (value) => value?.$ref
  ? value.$ref.slice(2).split('/').reduce((node, key) => node[key], spec)
  : value;
const jsonBlock = (value) => `<pre><code class="language-json">${escape(JSON.stringify(value, null, 2))}</code></pre>`;

let guide = execFileSync('php', ['-r', String.raw`
require $argv[1];
$converter = new League\CommonMark\GithubFlavoredMarkdownConverter([
    'html_input' => 'strip',
    'allow_unsafe_links' => false,
]);
echo $converter->convert(stream_get_contents(STDIN));
`, resolve(root, 'vendor/autoload.php')], { input: markdown, encoding: 'utf8', maxBuffer: 8 * 1024 * 1024 });

const navigation = [];
const headingIds = new Set();
guide = guide.replace(/<h([1-6])>(.*?)<\/h\1>/g, (_, level, title) => {
  const plain = title.replace(/<[^>]+>/g, '');
  const base = slug(plain);
  let id = base;
  let count = 2;
  while (headingIds.has(id)) id = `${base}-${count++}`;
  headingIds.add(id);
  if (level === '2') navigation.push(`<a href="#${id}">${title}</a>`);
  return `<h${level} id="${id}">${title}</h${level}>`;
});

function typeLabel(schema = {}) {
  if (schema.$ref) {
    const name = schema.$ref.split('/').at(-1);
    return `<a href="#schema-${slug(name)}">${escape(name)}</a>`;
  }
  if (schema.type === 'array') return `array of ${typeLabel(schema.items)}`;
  return `${escape(schema.type ?? 'object')}${schema.format ? ` (${escape(schema.format)})` : ''}${schema.nullable ? ' · nullable' : ''}`;
}

function constraints(schema = {}) {
  const entries = [];
  if (schema.enum) entries.push(`Allowed: ${schema.enum.map((value) => JSON.stringify(value)).join(', ')}`);
  for (const [key, label] of Object.entries({ minimum: 'Minimum', maximum: 'Maximum', minLength: 'Min length', maxLength: 'Max length', minItems: 'Min items', maxItems: 'Max items', default: 'Default', pattern: 'Pattern' })) {
    if (schema[key] !== undefined) entries.push(`${label}: ${JSON.stringify(schema[key])}`);
  }
  if (schema.readOnly) entries.push('Response only');
  if (schema.description) entries.push(schema.description);
  return entries.map(escape).join('<br>');
}

function table(headers, rows) {
  return `<div class="table-wrap"><table><thead><tr>${headers.map((header) => `<th scope="col">${header}</th>`).join('')}</tr></thead><tbody>${rows.map((row) => `<tr>${row.map((cell) => `<td>${cell}</td>`).join('')}</tr>`).join('')}</tbody></table></div>`;
}

function fields(schema) {
  const resolved = resolveReference(schema);
  if (!resolved?.properties) return `<p>${typeLabel(schema)}</p>`;
  return table(['Field', 'Type', 'Required', 'Details'], Object.entries(resolved.properties).map(([name, property]) => [
    `<code>${escape(name)}</code>`, typeLabel(property), resolved.required?.includes(name) ? 'Yes' : '—', constraints(property),
  ]));
}

const operations = [];
for (const [path, pathItem] of Object.entries(spec.paths)) {
  for (const [method, operation] of Object.entries(pathItem)) {
    if (!['get', 'post', 'put', 'patch', 'delete', 'head', 'options'].includes(method)) continue;
    const parameters = [...(pathItem.parameters ?? []), ...(operation.parameters ?? [])].map(resolveReference);
    const protectedRoute = (operation.security ?? spec.security ?? []).length > 0;
    const access = protectedRoute ? 'Bearer token' : 'Public';
    const request = resolveReference(operation.requestBody)?.content?.['application/json']?.schema;
    const responses = Object.entries(operation.responses).map(([status, response]) => {
      const resolved = resolveReference(response);
      const schema = resolved.content?.['application/json']?.schema;
      return `<h4>${escape(status)} response</h4><p>${escape(resolved.description ?? '')}</p>${schema ? fields(schema) : ''}`;
    }).join('');
    operations.push({ path, method, access, html: `<details class="endpoint" id="${escape(operation.operationId)}" data-method="${method}" data-access="${protectedRoute ? 'protected' : 'public'}">
      <summary><span class="method ${method}">${method.toUpperCase()}</span><code>${escape(path)}</code><span class="access">${access}</span></summary>
      <div class="endpoint-body"><h3>${escape(operation.summary)}</h3><p>${escape(operation.description ?? '')}</p>
      <p class="muted">${protectedRoute ? 'Requires an active administrator, token permissions, and applicable organizational scope.' : 'No bearer token required.'} <a href="#${escape(operation.operationId)}">Link to operation</a></p>
      ${parameters.length ? `<h4>Parameters</h4>${table(['Name', 'Location', 'Type', 'Required', 'Details'], parameters.map((parameter) => [`<code>${escape(parameter.name)}</code>`, escape(parameter.in), typeLabel(parameter.schema), parameter.required ? 'Yes' : 'No', [constraints(parameter.schema), escape(parameter.description ?? '')].filter(Boolean).join('<br>')]))}` : '<p>No path or query parameters.</p>'}
      ${request ? `<h4>JSON request body</h4>${fields(request)}<details class="raw-schema"><summary>Request schema JSON</summary>${jsonBlock(resolveReference(request))}</details>` : ''}
      ${responses}</div></details>` });
  }
}

const schemaReference = Object.entries(spec.components.schemas).map(([name, schema]) => `<details class="schema" id="schema-${slug(name)}"><summary>${escape(name)}</summary><div class="endpoint-body"><p>${escape(schema.description ?? '')}</p>${fields(schema)}<details class="raw-schema"><summary>Schema JSON</summary>${jsonBlock(schema)}</details></div></details>`).join('\n');

guide = guide.replace(/<table>/g, '<div class="table-wrap"><table>').replace(/<\/table>/g, '</table></div>');

const html = `<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="Consumer documentation for the Catholic Tanzania Core API: directory browsing, authentication, administration, examples, and a complete endpoint reference.">
<title>Catholic Tanzania API · Developer documentation</title>
<style>
:root{color-scheme:light;--paper:#fafbf8;--surface:#fff;--ink:#172c25;--muted:#586960;--line:#dce4dc;--green:#176447;--soft:#eaf3eb;--gold:#9c6819;--code:#132b24}
*{box-sizing:border-box}html{scroll-behavior:smooth;scroll-padding-top:95px}body{margin:0;background:var(--paper);color:var(--ink);font:16px/1.7 system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}a{color:var(--green);text-underline-offset:3px}a:hover{text-decoration-thickness:2px}a:focus-visible,button:focus-visible,input:focus-visible,select:focus-visible,summary:focus-visible{outline:3px solid #b78224;outline-offset:4px}button,input,select{font:inherit}button,summary{cursor:pointer}button{border:1px solid var(--line);border-radius:7px;padding:.4rem .8rem;background:var(--surface);color:var(--ink)}button:hover{background:var(--soft)}[hidden]{display:none!important}.skip{position:fixed;top:-80px;left:16px;z-index:10;background:white;padding:12px}.skip:focus{top:12px}
.topbar{position:sticky;top:0;z-index:5;height:72px;display:flex;align-items:center;justify-content:space-between;gap:20px;padding:0 32px;background:rgba(250,251,248,.98);border-bottom:1px solid var(--line)}.brand{display:flex;gap:12px;align-items:center;font-weight:750;text-decoration:none;color:var(--ink)}.mark{width:34px;height:34px;display:grid;place-items:center;background:var(--green);color:white;border-radius:8px;font-size:23px}.brand small{font-weight:500;color:var(--muted)}.top-links{display:flex;gap:20px;align-items:center;font-size:14px}.top-links a{text-decoration:none}.version{font-size:12px;letter-spacing:.06em;color:var(--green);background:var(--soft);padding:3px 9px;border-radius:20px}.layout{display:grid;grid-template-columns:254px minmax(0,1fr);max-width:1500px;margin:auto}.sidebar{position:sticky;top:72px;align-self:start;height:calc(100vh - 72px);overflow-y:auto;padding:32px 24px;border-right:1px solid var(--line);font-size:13px}.nav-label{font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:var(--muted);font-weight:750;margin:0 0 12px}.sidebar a{display:block;text-decoration:none;color:var(--muted);padding:6px 10px;border-radius:6px;line-height:1.5}.sidebar a:hover,.sidebar a.active{background:var(--soft);color:var(--green)}.sidebar .divider{margin-top:25px;padding-top:22px;border-top:1px solid var(--line)}main{min-width:0;padding:45px clamp(24px,4vw,70px) 100px;max-width:1200px}.eyebrow{color:var(--green);font-size:12px;font-weight:750;letter-spacing:.12em;text-transform:uppercase;margin:0 0 16px}.intro-cards{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin:25px 0 38px}.intro-card{border:1px solid var(--line);background:white;padding:17px 20px;border-radius:10px;font-size:13px;color:var(--muted)}.intro-card strong{display:block;color:var(--ink);font-size:18px;margin-bottom:3px}.intro-card a{text-decoration:none}h1,h2,h3,h4{line-height:1.25;letter-spacing:-.025em}h1{font-size:clamp(32px,4vw,48px);max-width:750px;margin:0 0 14px}h2{font-size:28px;margin:56px 0 22px;padding-top:28px;border-top:1px solid var(--line)}h3{font-size:20px;margin:32px 0 14px}h4{font-size:16px;margin:28px 0 12px}p{margin:0 0 18px}li{margin:8px 0}strong{font-weight:650}.muted{color:var(--muted);font-size:14px}code{font-family:ui-monospace,SFMono-Regular,Consolas,monospace;font-size:.86em;background:#edf1eb;padding:.12em .3em;border-radius:4px;overflow-wrap:anywhere}pre{position:relative;background:var(--code);color:#e6f2e9;border-radius:10px;padding:23px;overflow-x:auto;line-height:1.65;margin:22px 0;font-size:14px}pre code{padding:0;background:transparent;color:inherit;overflow-wrap:normal;font-size:inherit;white-space:pre}pre:has(.copy){padding-top:47px}.copy{position:absolute;right:10px;top:9px;font-size:11px;padding:3px 9px;background:#254538;color:#f2f6f2;border-color:#426252}.copy:hover{background:#38604c}.table-wrap{overflow-x:auto;margin:20px 0 25px;border:1px solid var(--line);border-radius:8px}table{border-collapse:collapse;width:100%;font-size:13px;background:white}th{text-align:left;background:#f0f4ee;color:var(--muted);font-size:12px}th,td{padding:12px 14px;border-bottom:1px solid var(--line);vertical-align:top}tr:last-child td{border-bottom:0}td code{font-size:12px}td{min-width:95px}td:last-child{min-width:180px}blockquote{border-left:3px solid var(--green);padding:12px 20px;margin:25px 0;background:var(--soft)}.reference-intro{max-width:680px}.filters{display:flex;gap:12px;flex-wrap:wrap;padding:18px;background:var(--soft);border:1px solid var(--line);border-radius:10px;margin:25px 0 12px}.filter{display:flex;flex-direction:column;gap:5px;flex:1;min-width:130px}.filter:first-child{flex:3;min-width:200px}.filter label{font-size:12px;font-weight:650}.filter input,.filter select{width:100%;border:1px solid #bacbbf;padding:9px 12px;border-radius:6px;background:white;min-height:44px;font-size:14px}.endpoint,.schema{border:1px solid var(--line);border-radius:8px;margin:10px 0;background:white;scroll-margin-top:95px}.endpoint>summary{display:flex;align-items:center;gap:12px;padding:15px;list-style:none;font-size:13px}.endpoint>summary::-webkit-details-marker{display:none}.endpoint>summary:after{content:'+';font-size:20px;margin-left:5px;color:var(--muted)}.endpoint[open]>summary:after{content:'−'}.endpoint[open]>summary,.schema[open]>summary{border-bottom:1px solid var(--line);background:#f4f7f1}.endpoint>summary code{background:none;font-size:13px}.method{font-size:10px;font-weight:800;letter-spacing:.035em;min-width:47px;text-align:center;border-radius:4px;padding:3px 5px;background:#edeaf9;color:#6845a4}.method.get{color:#176044;background:#e8f4ec}.method.post{color:#85530f;background:#fff1d6}.method.put,.method.patch{color:#30558d;background:#eaf0fa}.access{margin-left:auto;font-size:11px;color:var(--muted);white-space:nowrap}.endpoint-body{padding:5px 22px 20px;min-width:0}.endpoint-body h3{margin-top:20px}.schema>summary{padding:15px;font:600 14px ui-monospace,monospace;overflow-wrap:anywhere}.raw-schema{margin:20px 0;font-size:13px}.raw-schema summary{color:var(--green)}footer{margin-top:55px;padding-top:25px;border-top:1px solid var(--line);font-size:13px;color:var(--muted)}#menu-toggle{display:none}.empty{padding:25px;background:white;border:1px solid var(--line);border-radius:8px}noscript p{background:var(--soft);padding:15px}
@media(min-width:1500px){.layout{border-left:1px solid var(--line);border-right:1px solid var(--line)}}
@media(max-width:900px){.layout{grid-template-columns:215px minmax(0,1fr)}.sidebar{padding:25px 14px}main{padding:32px 24px 70px}.intro-cards{gap:8px}.intro-card{padding:13px}.topbar{padding:0 20px}.brand small{display:none}}
@media(max-width:680px){.topbar{height:64px;padding:0 16px;gap:8px}.brand{font-size:13px;gap:8px}.mark{width:29px;height:29px}.version,.top-links>a{display:none}.top-links{gap:8px}.layout{display:block}.sidebar{display:none;position:fixed;top:64px;left:0;bottom:0;height:auto;width:min(310px,90vw);z-index:6;background:var(--paper);box-shadow:12px 0 28px #172c251a;padding:25px}.sidebar.visible{display:block}#menu-toggle{display:block;font-size:13px}main{padding:28px 18px 60px}h1{font-size:34px}h2{font-size:24px}body{font-size:15px}.intro-cards{grid-template-columns:1fr}.intro-card strong{font-size:16px}.intro-card{padding:12px 16px}.endpoint>summary{gap:7px;padding:12px 9px;flex-wrap:wrap}.endpoint>summary code{font-size:11px;flex:1;min-width:0}.access{display:none}.endpoint-body{padding:4px 12px 15px}pre{padding:18px;font-size:12px}.filters{padding:12px}.filter{min-width:100%}}
@media(prefers-reduced-motion:reduce){html{scroll-behavior:auto}}
@media print{.topbar,.sidebar,.filters,.copy,.skip,.intro-cards{display:none!important}.layout{display:block}main{max-width:none;padding:0}body{background:white;color:black;font-size:10pt}h1{font-size:26pt}h2{font-size:18pt;margin-top:24px}h3{font-size:14pt}pre{background:#f1f3ef;color:black;white-space:pre-wrap;overflow:visible}pre code{white-space:pre-wrap;overflow-wrap:anywhere}.table-wrap{overflow:visible}table{font-size:8pt}th,td{min-width:0!important;padding:6px}.endpoint,.schema{break-inside:avoid}a{color:inherit}.muted{font-size:9pt}}
</style>
</head>
<body>
<a class="skip" href="#main">Skip to content</a>
<header class="topbar"><a class="brand" href="#main"><span class="mark" aria-hidden="true">✦</span><span>Catholic Tanzania <small>/ API docs</small></span></a><div class="top-links"><span class="version">v${escape(spec.info.version)}</span><a href="consumer-guide.md" download>Markdown guide ↗</a><a href="openapi.json" download>OpenAPI JSON ↗</a><button id="menu-toggle" type="button" aria-expanded="false" aria-controls="navigation">Contents</button></div></header>
<div class="layout"><aside class="sidebar" id="navigation"><nav aria-label="Documentation contents"><p class="nav-label">Consumer guide</p>${navigation.join('\n')}<p class="nav-label divider">API reference</p><a href="#endpoint-reference">All ${operations.length} operations</a><a href="#schema-reference">Request &amp; response schemas</a><p class="nav-label divider">Downloads</p><a href="consumer-guide.md" download>Markdown guide</a><a href="openapi.json" download>OpenAPI specification</a></nav></aside>
<main id="main"><p class="eyebrow">Developer documentation / API v1</p><div class="intro-cards"><div class="intro-card"><strong>Browse the directory</strong>Public reads · No token required</div><div class="intro-card"><strong>Manage your community</strong>Scoped administrator access</div><div class="intro-card"><a href="#endpoint-reference"><strong>${operations.length} documented operations ↗</strong></a>Examples, fields &amp; response schemas</div></div><article id="guide">${guide}</article>
<section aria-labelledby="endpoint-reference"><h2 id="endpoint-reference">Endpoint reference</h2><p class="reference-intro">Every operation in API v1. Paths are relative to your API base URL. Open an operation for its parameters, request body, and response fields. Select a named type to read its schema.</p><div class="filters" hidden><div class="filter"><label for="endpoint-search">Find an endpoint</label><input id="endpoint-search" type="search" placeholder="Search path, action, or field…" autocomplete="off"></div><div class="filter"><label for="method-filter">HTTP method</label><select id="method-filter"><option value="">All methods</option><option>GET</option><option>POST</option><option>PUT</option><option>PATCH</option></select></div><div class="filter"><label for="access-filter">Access</label><select id="access-filter"><option value="">All access</option><option value="public">Public</option><option value="protected">Bearer token</option></select></div></div><p id="result-count" class="muted" role="status">${operations.length} operations</p><noscript><p>All operations are listed below. Use your browser's Find command to locate a path.</p></noscript><div id="endpoints">${operations.map((operation) => operation.html).join('\n')}</div><p class="empty" id="empty-results" hidden>No operations match. Try a resource name such as “parishes” or clear the filters.</p></section>
<section aria-labelledby="schema-reference"><h2 id="schema-reference">Schema reference</h2><p>Input schemas describe creation bodies. Patch schemas describe partial updates. Public schemas omit contact fields and administrative descriptions. “Required” indicates presence required by the schema; nullable fields may contain JSON null.</p>${schemaReference}</section>
<footer>Catholic Tanzania Core API · v${escape(spec.info.version)} · <a href="consumer-guide.md" download>Download guide</a> · <a href="openapi.json" download>Download OpenAPI</a> · <a href="#main">Back to top ↑</a></footer>
</main></div>
<script>
const endpoints = [...document.querySelectorAll('.endpoint')];
const search = document.querySelector('#endpoint-search');
const methodFilter = document.querySelector('#method-filter');
const accessFilter = document.querySelector('#access-filter');
const searchable = endpoints.map((element) => ({ element, text: element.textContent.toLowerCase() }));
document.querySelector('.filters').hidden = false;
function filterEndpoints() {
  const terms = search.value.toLowerCase().trim().split(/\\s+/).filter(Boolean);
  let count = 0;
  for (const { element, text } of searchable) {
    const visible = terms.every((term) => text.includes(term)) && (!methodFilter.value || element.dataset.method === methodFilter.value.toLowerCase()) && (!accessFilter.value || element.dataset.access === accessFilter.value);
    element.hidden = !visible;
    if (visible) count++;
  }
  document.querySelector('#result-count').textContent = count + ' of ' + endpoints.length + ' operations';
  document.querySelector('#empty-results').hidden = count !== 0;
}
search.addEventListener('input', filterEndpoints);
methodFilter.addEventListener('change', filterEndpoints);
accessFilter.addEventListener('change', filterEndpoints);
const menu = document.querySelector('#menu-toggle');
const sidebar = document.querySelector('.sidebar');
function closeMenu() { sidebar.classList.remove('visible'); menu.setAttribute('aria-expanded', 'false'); }
menu.addEventListener('click', () => { const open = sidebar.classList.toggle('visible'); menu.setAttribute('aria-expanded', String(open)); });
sidebar.addEventListener('click', (event) => { if (event.target.closest('a')) closeMenu(); });
document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && sidebar.classList.contains('visible')) { closeMenu(); menu.focus(); } });
function openLinkedDetails() {
  let id;
  try { id = decodeURIComponent(location.hash.slice(1)); } catch { return; }
  const target = document.getElementById(id);
  if (!target) return;
  if (target.matches('details')) {
    if (target.matches('.endpoint') && target.hidden) { search.value = ''; methodFilter.value = ''; accessFilter.value = ''; filterEndpoints(); }
    target.open = true;
  }
  requestAnimationFrame(() => target.scrollIntoView());
}
addEventListener('hashchange', openLinkedDetails);
if (location.hash) openLinkedDetails();
for (const block of document.querySelectorAll('pre')) {
  if (!navigator.clipboard?.writeText) continue;
  const code = block.querySelector('code');
  if (!code) continue;
  const button = document.createElement('button');
  button.className = 'copy'; button.type = 'button'; button.textContent = 'Copy'; button.setAttribute('aria-label', 'Copy code example');
  button.addEventListener('click', async () => {
    try { await navigator.clipboard.writeText(code.textContent); button.textContent = 'Copied'; }
    catch { button.textContent = 'Select text to copy'; }
    setTimeout(() => { button.textContent = 'Copy'; }, 1800);
  });
  block.append(button);
}
if ('IntersectionObserver' in window) {
  const links = [...sidebar.querySelectorAll('a[href^="#"]')];
  const observer = new IntersectionObserver((entries) => {
    for (const entry of entries) if (entry.isIntersecting) {
      for (const link of links) { const active = link.hash === '#' + entry.target.id; link.classList.toggle('active', active); if (active) link.setAttribute('aria-current', 'location'); else link.removeAttribute('aria-current'); }
    }
  }, { rootMargin: '-80px 0px -65% 0px', threshold: 0 });
  document.querySelectorAll('main h2').forEach((heading) => observer.observe(heading));
}
</script>
</body>
</html>
`;

writeFileSync(resolve(directory, 'index.html'), html);
console.log(`Built docs/api/index.html: ${operations.length} operations and ${Object.keys(spec.components.schemas).length} schemas.`);
