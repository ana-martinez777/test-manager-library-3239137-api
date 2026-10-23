// Prueba de contrato (T-16, RNF-4) y de concurrencia (T-13, RNF-2) contra la API levantada.
// Uso (Git Bash):  ADMIN_USERNAME=admin ADMIN_PASSWORD=... node scripts/contract-test.mjs
// Requiere Node 18+. Crea datos de prueba con valores aleatorios; no borra nada (RN-5).

const BASE = process.env.API_URL ?? 'http://localhost:8000/api/v1';
const ADMIN_USER = process.env.ADMIN_USERNAME ?? 'admin';
const ADMIN_PASS = process.env.ADMIN_PASSWORD;
if (!ADMIN_PASS) {
  console.error('Define ADMIN_PASSWORD en el entorno.');
  process.exit(2);
}

let passed = 0;
let failed = 0;
const rnd = () => Math.random().toString(36).slice(2, 10);
const isbn = () => '9' + String(Math.floor(Math.random() * 1e12)).padStart(12, '0');
const today = new Date().toISOString().slice(0, 10);
const NOVELA = '11111111-1111-4111-8111-111111111111';
const CIENCIA = '22222222-2222-4222-8222-222222222222';
const MISSING = '99999999-9999-4999-8999-999999999999';

async function call(method, path, { token, body, form } = {}) {
  const headers = { Accept: 'application/json' };
  if (token) headers.Authorization = `Bearer ${token}`;
  let payload;
  if (form) payload = form;
  else if (body !== undefined) {
    headers['Content-Type'] = 'application/json';
    payload = JSON.stringify(body);
  }
  const res = await fetch(BASE + path, { method, headers, body: payload });
  const type = res.headers.get('content-type') ?? '';
  const data = type.includes('json') ? await res.json() : null;
  return { status: res.status, data, type };
}

function check(name, ok, info = '') {
  if (ok) {
    passed++;
    console.log(`  ✔ ${name}`);
  } else {
    failed++;
    console.log(`  ✘ ${name} ${info}`);
  }
}

function isErrorBody(r, status, code) {
  const d = r.data ?? {};
  return r.status === status && d.code === code && typeof d.message === 'string' && Array.isArray(d.details)
    && Object.keys(d).sort().join(',') === 'code,details,message';
}

function expectError(name, r, status, code) {
  check(`${name} → ${status} ${code}`, isErrorBody(r, status, code), `(recibido ${r.status} ${JSON.stringify(r.data)})`);
}

const png = (bytes) => {
  const sig = Uint8Array.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]);
  const out = new Uint8Array(bytes);
  out.set(sig);
  return out;
};
const coverForm = (data, name = 'cover.png', type = 'image/png') => {
  const form = new FormData();
  form.append('file', new Blob([data], { type }), name);
  return form;
};

console.log(`Contrato de Manager Library contra ${BASE}\n`);

console.log('3.1 Autenticación y salud (HU-01)');
let r = await call('GET', '/health');
check('GET /health → 200 {"status":"ok"} sin token', r.status === 200 && r.data?.status === 'ok');
expectError('GET /books sin token', await call('GET', '/books'), 401, 'INVALID_CREDENTIALS');
expectError('GET /books con token falso', await call('GET', '/books', { token: 'x.y.z' }), 401, 'INVALID_CREDENTIALS');
const badUser = await call('POST', '/auth/login', { body: { username: 'no-existe-' + rnd(), password: 'x' } });
const badPass = await call('POST', '/auth/login', { body: { username: ADMIN_USER, password: 'mala-' + rnd() } });
expectError('login con usuario inexistente', badUser, 401, 'INVALID_CREDENTIALS');
expectError('login con clave incorrecta', badPass, 401, 'INVALID_CREDENTIALS');
check('CA-01.2: mismo mensaje para usuario o clave incorrectos', badUser.data?.message === badPass.data?.message);
r = await call('POST', '/auth/login', { body: { username: ` ${ADMIN_USER.toUpperCase()} `, password: ADMIN_PASS } });
check('login admin → 200, Bearer, 28800 s, rol admin (T-17)', r.status === 200 && r.data?.token_type === 'Bearer' && r.data?.expires_in === 28800 && r.data?.role === 'admin');
const admin = r.data?.access_token;
const [, payload] = (admin ?? '..').split('.');
const claims = JSON.parse(Buffer.from(payload || 'e30', 'base64url').toString());
check('el token lleva sub y role, sin datos personales', claims.sub && claims.role === 'admin' && !('username' in claims) && claims.exp - claims.iat === 28800);

console.log('\n3.2 Usuarios (HU-02)');
const librarianName = 'Bibliotecaria' + rnd();
r = await call('POST', '/users', { token: admin, body: { username: ` ${librarianName} `, password: 'clave-123', role: 'librarian' } });
check('POST /users → 201, nombre en minúsculas y recortado', r.status === 201 && r.data?.username === librarianName.toLowerCase());
check('la respuesta no incluye password ni password_hash (RNF-3)', r.status === 201 && !JSON.stringify(r.data).includes('password'));
expectError('usuario repetido', await call('POST', '/users', { token: admin, body: { username: librarianName.toUpperCase(), password: 'x', role: 'librarian' } }), 409, 'USERNAME_TAKEN');
expectError('pedir rol admin por la API', await call('POST', '/users', { token: admin, body: { username: 'jefe' + rnd(), password: 'x', role: 'admin' } }), 422, 'VALIDATION_ERROR');
r = await call('POST', '/auth/login', { body: { username: librarianName, password: 'clave-123' } });
const librarian = r.data?.access_token;
check('login bibliotecaria → rol librarian', r.status === 200 && r.data?.role === 'librarian');
expectError('bibliotecaria crea usuarios', await call('POST', '/users', { token: librarian, body: { username: 'x' + rnd(), password: 'x', role: 'librarian' } }), 403, 'FORBIDDEN_ROLE');

console.log('\n3.3 Categorías');
r = await call('GET', '/categories', { token: librarian });
check('GET /categories → las 5, por nombre', r.status === 200 && r.data?.map((c) => c.name).join(',') === 'Ciencia,Historia,Infantil,Novela,Tecnología');

console.log('\n3.4 Libros (HU-03, HU-04)');
const tag = 'Contrato ' + rnd();
const newBook = async (title, copies, category = NOVELA) =>
  call('POST', '/books', { token: admin, body: { title: `${tag} ${title}`, author: 'Autora Prueba', isbn: isbn(), category_id: category, total_copies: copies } });
r = await newBook('Alfa', 4);
const bookA = r.data;
check('POST /books → 201, disponibles = total, cover_key null, categoría con nombre', r.status === 201 && bookA.available_copies === 4 && bookA.cover_key === null && bookA.category?.name === 'Novela');
expectError('bibliotecaria crea libro', await call('POST', '/books', { token: librarian, body: { title: 'x' } }), 403, 'FORBIDDEN_ROLE');
expectError('ISBN repetido entre activos', await call('POST', '/books', { token: admin, body: { title: 'x', author: 'y', isbn: bookA.isbn, category_id: NOVELA, total_copies: 1 } }), 409, 'ISBN_TAKEN');
expectError('ISBN de 12 dígitos', await call('POST', '/books', { token: admin, body: { title: 'x', author: 'y', isbn: '978030747472', category_id: NOVELA, total_copies: 1 } }), 422, 'VALIDATION_ERROR');
expectError('total_copies = 0', await call('POST', '/books', { token: admin, body: { title: 'x', author: 'y', isbn: isbn(), category_id: NOVELA, total_copies: 0 } }), 422, 'VALIDATION_ERROR');
expectError('campos faltantes', await call('POST', '/books', { token: admin, body: {} }), 422, 'VALIDATION_ERROR');
await newBook('Beta', 1);
r = await call('GET', `/books?q=${encodeURIComponent(tag.toLowerCase())}&category_id=${NOVELA}&page=1&size=1`, { token: librarian });
check('GET /books: búsqueda parcial, paginada, con total', r.status === 200 && r.data?.total === 2 && r.data?.items.length === 1 && r.data?.size === 1 && r.data.items[0].title.endsWith('Alfa'));
expectError('size = 51', await call('GET', '/books?size=51', { token: librarian }), 422, 'VALIDATION_ERROR');
check('GET /books/{id} → 200', (await call('GET', `/books/${bookA.id}`, { token: librarian })).status === 200);
expectError('GET /books/{id} inexistente', await call('GET', `/books/${MISSING}`, { token: librarian }), 404, 'NOT_FOUND');
expectError('GET /books/{id} con id mal formado', await call('GET', '/books/abc', { token: librarian }), 404, 'NOT_FOUND');
r = await call('PUT', `/books/${bookA.id}`, { token: admin, body: { title: `${tag} Alfa editado`, author: 'Autora', category_id: CIENCIA, total_copies: 6 } });
check('PUT /books/{id}: total 4→6 sube disponibles 4→6', r.status === 200 && r.data?.available_copies === 6 && r.data?.category?.name === 'Ciencia');

console.log('\n    Portada (CA-03.4)');
r = await call('PUT', `/books/${bookA.id}/cover`, { token: admin, form: coverForm(png(1024)) });
check('PUT cover PNG → 200 con cover_key opaco', r.status === 200 && typeof r.data?.cover_key === 'string' && !r.data.cover_key.includes('/'), JSON.stringify(r.data));
r = await call('GET', `/books/${bookA.id}/cover`, { token: librarian });
check('GET cover → 200 image/png', r.status === 200 && r.type.startsWith('image/png'));
r = await call('PUT', `/books/${bookA.id}/cover`, { token: admin, form: coverForm(png(2 * 1024 * 1024)) });
check('PUT cover PNG de 2 MB exactos → 200', r.status === 200, `(recibido ${r.status})`);
expectError('PUT cover de 3 MB', await call('PUT', `/books/${bookA.id}/cover`, { token: admin, form: coverForm(png(3 * 1024 * 1024)) }), 422, 'VALIDATION_ERROR');
expectError('PUT cover GIF con extensión .png', await call('PUT', `/books/${bookA.id}/cover`, { token: admin, form: coverForm(new TextEncoder().encode('GIF89a......'), 'falso.png') }), 422, 'VALIDATION_ERROR');
check('DELETE cover → 204', (await call('DELETE', `/books/${bookA.id}/cover`, { token: admin })).status === 204);
r = await call('GET', `/books/${bookA.id}`, { token: admin });
check('sin portada, cover_key es null (no cadena vacía)', r.data?.cover_key === null);
expectError('GET cover sin portada', await call('GET', `/books/${bookA.id}/cover`, { token: admin }), 404, 'NOT_FOUND');

console.log('\n3.5 Socios (HU-05)');
const newMember = async (name) => (await call('POST', '/members', { token: librarian, body: { full_name: name, document_number: 'CT-' + rnd() } })).data;
r = await call('POST', '/members', { token: librarian, body: { full_name: 'Ana Prueba', document_number: 'CT-' + rnd() } });
const ana = r.data;
check('POST /members (bibliotecaria) → 201', r.status === 201 && ana.full_name === 'Ana Prueba');
expectError('documento repetido', await call('POST', '/members', { token: admin, body: { full_name: 'Otra', document_number: ana.document_number } }), 409, 'DOCUMENT_TAKEN');
r = await call('GET', `/members?q=${encodeURIComponent(ana.document_number)}`, { token: librarian });
check('GET /members busca por documento', r.status === 200 && r.data?.total === 1);
check('DELETE /members/{id} no existe (CA-05.3)', (await call('DELETE', `/members/${ana.id}`, { token: admin })).status !== 204);

console.log('\n3.6 Préstamos (HU-06 a HU-09)');
const one = (await newBook('Único', 1)).data;
const b1 = (await newBook('Uno', 5)).data;
const b2 = (await newBook('Dos', 5)).data;
const b3 = (await newBook('Tres', 5)).data;
const b4 = (await newBook('Cuatro', 5)).data;
expectError('libro repetido en el préstamo', await call('POST', '/loans', { token: librarian, body: { member_id: ana.id, items: [{ book_id: b1.id }, { book_id: b1.id }] } }), 422, 'DUPLICATE_BOOK_IN_LOAN');
expectError('4 líneas', await call('POST', '/loans', { token: librarian, body: { member_id: ana.id, items: [b1, b2, b3, b4].map((b) => ({ book_id: b.id })) } }), 422, 'VALIDATION_ERROR');
expectError('0 líneas', await call('POST', '/loans', { token: librarian, body: { member_id: ana.id, items: [] } }), 422, 'VALIDATION_ERROR');
expectError('socio inexistente', await call('POST', '/loans', { token: librarian, body: { member_id: MISSING, items: [{ book_id: b1.id }] } }), 404, 'NOT_FOUND');
r = await call('POST', '/loans', { token: librarian, body: { member_id: ana.id, items: [{ book_id: one.id }, { book_id: b1.id }] } });
const loan = r.data;
const due = loan && new Date(loan.items[0].due_at) - new Date(loan.loaned_at);
check('POST /loans → 201, open, vence a 14 días, datos congelados', r.status === 201 && loan.status === 'open' && due === 14 * 864e5 && loan.items.every((i) => i.returned_at === null && i.category_name === 'Novela'), JSON.stringify(r.data));
check('fechas ISO 8601 en UTC con Z', /Z$/.test(loan?.loaned_at ?? '') && /Z$/.test(loan?.items?.[0]?.due_at ?? ''));
r = await call('GET', `/books/${one.id}`, { token: librarian });
check('cada línea descuenta 1 ejemplar', r.data?.available_copies === 0);
const luis = await newMember('Luis Prueba');
r = await call('POST', '/loans', { token: librarian, body: { member_id: luis.id, items: [{ book_id: b2.id }, { book_id: one.id }] } });
expectError('sin ejemplares', r, 409, 'BOOK_NOT_AVAILABLE');
check('…y no se registra nada (b2 conserva sus 5)', (await call('GET', `/books/${b2.id}`, { token: librarian })).data?.available_copies === 5);
r = await call('POST', '/loans', { token: librarian, body: { member_id: ana.id, items: [{ book_id: b2.id }, { book_id: b3.id }] } });
expectError('más de 3 libros sin devolver (tiene 2, pide 2)', r, 422, 'MEMBER_LIMIT_EXCEEDED');
check('…el mensaje informa el conteo actual', /2/.test(r.data?.message ?? ''));
r = await call('GET', `/members/${ana.id}`, { token: librarian });
check('GET /members/{id} → active_loans = 2', r.status === 200 && r.data?.active_loans === 2);
expectError('dar de baja un libro con préstamos abiertos', await call('DELETE', `/books/${one.id}`, { token: admin }), 409, 'BOOK_HAS_OPEN_LOANS');
expectError('PUT total por debajo de lo prestado', await call('PUT', `/books/${b1.id}`, { token: admin, body: { title: 'x', author: 'y', category_id: NOVELA, total_copies: 0 } }), 422, 'VALIDATION_ERROR');

const item = loan.items.find((i) => i.book_id === one.id);
r = await call('POST', `/loans/${loan.id}/items/${item.id}/return`, { token: librarian });
check('devolver una línea → 200 con returned_at', r.status === 200 && typeof r.data?.returned_at === 'string');
expectError('devolver la misma línea otra vez', await call('POST', `/loans/${loan.id}/items/${item.id}/return`, { token: librarian }), 409, 'ITEM_ALREADY_RETURNED');
check('…nunca suma dos veces (disponibles = 1 = total)', (await call('GET', `/books/${one.id}`, { token: librarian })).data?.available_copies === 1);
const other = loan.items.find((i) => i.id !== item.id);
await call('POST', `/loans/${loan.id}/items/${other.id}/return`, { token: librarian });
r = await call('GET', `/loans/${loan.id}`, { token: librarian });
check('todas devueltas → closed', r.status === 200 && r.data?.status === 'closed');
expectError('GET /loans/{id} inexistente', await call('GET', `/loans/${MISSING}`, { token: librarian }), 404, 'NOT_FOUND');
r = await call('GET', `/loans?member_id=${ana.id}&status=closed&from=${today}&to=${today}`, { token: librarian });
check('GET /loans filtra por socio, estado y fechas', r.status === 200 && r.data?.total === 1 && r.data.items[0].id === loan.id);
expectError('GET /loans con rango invertido', await call('GET', '/loans?from=2026-10-31&to=2026-10-01', { token: librarian }), 422, 'INVALID_DATE_RANGE');
r = await call('GET', '/loans/overdue', { token: librarian });
check('GET /loans/overdue → 200 con lista items', r.status === 200 && Array.isArray(r.data?.items));
check('PUT/DELETE /loans/{id} no existen (CA-06.7)', (await call('DELETE', `/loans/${loan.id}`, { token: admin })).status !== 204);

console.log('\n    Concurrencia: dos préstamos del último ejemplar a la vez (RNF-2, ADR-002)');
const last = (await newBook('Último', 1)).data;
const [m1, m2] = [await newMember('Carrera Uno'), await newMember('Carrera Dos')];
const race = await Promise.all([m1, m2].map((m) => call('POST', '/loans', { token: librarian, body: { member_id: m.id, items: [{ book_id: last.id }] } })));
const statuses = race.map((x) => x.status).sort();
check('exactamente uno 201 y el otro 409 BOOK_NOT_AVAILABLE', statuses.join(',') === '201,409' && race.some((x) => isErrorBody(x, 409, 'BOOK_NOT_AVAILABLE')), `(recibido ${statuses})`);
check('disponibles = 0, nunca negativo', (await call('GET', `/books/${last.id}`, { token: librarian })).data?.available_copies === 0);

console.log('\n3.7 Reporte (HU-10, ADR-004)');
expectError('bibliotecaria pide el reporte', await call('GET', `/reports/most-borrowed?from=${today}&to=${today}`, { token: librarian }), 403, 'FORBIDDEN_ROLE');
expectError('fin anterior al inicio', await call('GET', '/reports/most-borrowed?from=2026-10-31&to=2026-10-01', { token: admin }), 422, 'INVALID_DATE_RANGE');
expectError('sin from/to', await call('GET', '/reports/most-borrowed', { token: admin }), 422, 'VALIDATION_ERROR');
r = await call('GET', '/reports/most-borrowed?from=1990-01-01&to=1990-01-31', { token: admin });
check('rango sin préstamos → lista vacía, no error', r.status === 200 && Array.isArray(r.data?.rows) && r.data.rows.length === 0);
await call('PUT', `/books/${b1.id}`, { token: admin, body: { title: `${tag} Uno RENOMBRADO`, author: 'Autora', category_id: NOVELA, total_copies: 5 } });
r = await call('GET', `/reports/most-borrowed?from=${today}&to=${today}`, { token: admin });
const row = r.data?.rows?.find((x) => x.book_id === b1.id);
check('tras renombrar, el reporte conserva el título congelado', r.status === 200 && row?.book_title === `${tag} Uno` && row?.times_borrowed === 1, JSON.stringify(row));
check('las filas no traen socios ni bibliotecarios', r.data?.rows?.every((x) => Object.keys(x).sort().join(',') === 'book_id,book_title,category_name,times_borrowed'));

console.log('\n    Baja lógica (ADR-003)');
const gone = (await newBook('Para baja', 1)).data;
check('DELETE /books/{id} sin préstamos → 204', (await call('DELETE', `/books/${gone.id}`, { token: admin })).status === 204);
expectError('el libro dado de baja da 404', await call('GET', `/books/${gone.id}`, { token: admin }), 404, 'NOT_FOUND');
r = await call('POST', '/books', { token: admin, body: { title: 'Reingreso', author: 'y', isbn: gone.isbn, category_id: NOVELA, total_copies: 1 } });
check('el ISBN de un libro dado de baja se puede volver a registrar', r.status === 201);

console.log(`\nResultado: ${passed} correctas, ${failed} fallidas`);
process.exit(failed === 0 ? 0 : 1);
