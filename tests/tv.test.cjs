const test = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');

test('TV entrega eventos distintos em ordem e não avança cursor em falha HTTP', async () => {
  const nomes = [], store = new Map();
  const elements = new Map();
  const el = id => {
    if (!elements.has(id)) elements.set(id, { classList: {add(){},remove(){}}, set textContent(v) { if(id==='overlay-nome') nomes.push(v); }, innerHTML: '' });
    return elements.get(id);
  };
  let falhar = false;
  const contexto = {
    window: {initialCursor: 0, eventDay: '2026-10-22', YT: {}},
    document: {addEventListener(){},getElementById:el, createElement:()=>({}),head:{appendChild(){}}},
    sessionStorage: {getItem:k=>store.get(k),setItem:(k,v)=>store.set(k,v)},
    AbortSignal, Date, console: {error(){}},
    setTimeout: (fn, ms) => { if(ms===2000) return 0; Promise.resolve().then(fn); return 1; }, clearTimeout(){},
    fetch: async url => {
      if(url.startsWith('api_senhas_recentes')) return {ok:true,text:async()=>''};
      if(falhar) return {ok:false};
      return {ok:true,json:async()=>({success:true,video_id:'5qap5aO4i9A',eventos:[{id:1,nome:'Pessoa A',sala:'Sala 1'},{id:2,nome:'Pessoa B',sala:'Sala 2'}]})};
    },
  };
  vm.createContext(contexto);vm.runInContext(fs.readFileSync('assets/app.js','utf8'),contexto);
  for(let i=0;i<40;i++) await Promise.resolve();
  assert.deepEqual(nomes,['Pessoa A','Pessoa B']);
  assert.equal(store.get('sga-tv-cursor-2026-10-22'),'2');
  falhar=true;await vm.runInContext('verificarNovaSenha()',contexto);
  assert.equal(store.get('sga-tv-cursor-2026-10-22'),'2');
  // Navegadores com armazenamento bloqueado mantêm a entrega durante a sessão.
  const contextoSemStorage={...contexto,sessionStorage:{getItem(){throw new Error('bloqueado');},setItem(){throw new Error('bloqueado');}}};
  falhar=false;nomes.length=0;
  vm.createContext(contextoSemStorage);vm.runInContext(fs.readFileSync('assets/app.js','utf8'),contextoSemStorage);
  for(let i=0;i<40;i++)await Promise.resolve();
  assert.deepEqual(nomes,['Pessoa A','Pessoa B']);
  assert.equal(vm.runInContext('cursor',contextoSemStorage),2);
});
