const test=require('node:test');
const assert=require('node:assert/strict');
const vm=require('node:vm');
const fs=require('node:fs');

function ambiente(storage=new Map()) {
    const osciladores=[],rampas=[];let falas=0;
    class AudioFake {
        constructor(){this.currentTime=10;this.state='suspended';this.destination={};}
        resume(){this.state='running';}
        createOscillator(){
            const dado={};osciladores.push(dado);
            return {frequency:{setValueAtTime:f=>dado.frequencia=f},connect(){},disconnect(){},start:t=>dado.inicio=t,stop:t=>dado.fim=t};
        }
        createGain(){return {gain:{setValueAtTime:v=>rampas.push(v),linearRampToValueAtTime:v=>rampas.push(v),exponentialRampToValueAtTime:v=>rampas.push(v)},connect(){},disconnect(){}};}
    }
    const contexto={window:{AudioContext:AudioFake,speechSynthesis:{speak(){falas++;}}},document:{addEventListener(){}},
        localStorage:{getItem:k=>storage.has(k)?storage.get(k):null,setItem:(k,v)=>storage.set(k,v)}};
    vm.createContext(contexto);vm.runInContext(fs.readFileSync('assets/toques.js','utf8'),contexto);
    return {api:contexto.window.sgaToques,osciladores,rampas,storage,falas:()=>falas};
}

test('cinco novos toques terminam antes da voz e não acionam síntese',()=>{
    const a=ambiente();assert.equal(a.api.listar().length,7);
    for(const opcao of a.api.listar().filter(t=>!['original','novosga'].includes(t.id))) {
        a.osciladores.length=0;a.rampas.length=0;
        assert.equal(a.api.salvar(opcao.id,100),true);a.api.tocar();
        assert.ok(a.osciladores.length>0,opcao.id);
        assert.ok(a.osciladores.every(o=>Number.isFinite(o.frequencia)&&o.frequencia>0&&o.inicio>=10&&o.fim<=10.4),opcao.id);
        assert.ok(Math.max(...a.rampas)>=.3,opcao.id+' tem presença sonora reforçada');
        assert.ok(a.rampas.every(v=>Number.isFinite(v)&&v>0&&v<=.55),opcao.id);
    }
    assert.equal(a.falas(),0);
});

test('seleção e volume do toque persistem; padrão Novo SGA é preservado',()=>{
    const a=ambiente();assert.equal(a.api.selecionado(),'novosga');assert.equal(a.api.volume(),1);
    a.api.salvar('dingdong',40);const b=ambiente(a.storage);
    assert.equal(b.api.selecionado(),'dingdong');assert.equal(b.api.volume(),.4);
    assert.equal(b.api.salvar('inexistente',20),false);assert.equal(b.api.selecionado(),'dingdong');
    assert.equal(b.api.salvar('suave',NaN),false);
});

test('volume zero silencia apenas o alerta',()=>{
    const a=ambiente();a.api.salvar('suave',0);a.api.tocar();
    assert.equal(a.osciladores.length,0);assert.equal(a.falas(),0);
});

test('preferências inválidas e storage bloqueado não impedem o painel',()=>{
    const a=ambiente(new Map([['sga-tv-toque','desconhecido'],['sga-tv-toque-volume','inválido']]));
    assert.equal(a.api.selecionado(),'novosga');assert.equal(a.api.volume(),1);
    const contexto={window:{},document:{addEventListener(){}},localStorage:{getItem(){throw new Error('bloqueado');},setItem(){throw new Error('bloqueado');}}};
    vm.createContext(contexto);vm.runInContext(fs.readFileSync('assets/toques.js','utf8'),contexto);
    assert.equal(contexto.window.sgaToques.salvar('cristal',60),false);
    assert.equal(contexto.window.sgaToques.selecionado(),'cristal');assert.equal(contexto.window.sgaToques.volume(),.6);
});
