const test=require('node:test');
const assert=require('node:assert/strict');
const vm=require('node:vm');
const fs=require('node:fs');
const app=fs.readFileSync('assets/app.js','utf8');
const codigo=app.slice(app.indexOf('function falarSenha('),app.indexOf('async function atualizarRodape('));
for (const resultado of ['fim','erro','timeout','excecao']) {
    for (const mudo of [false,true]) test(`Vídeo restaura estado ${mudo} após ${resultado}`,async()=>{
        let silenciado=mudo,desmutou=0,voz;const timers=[];
        const contexto={audioAtivado:true,ytPlayer:{isMuted:()=>silenciado,mute(){silenciado=true;},unMute(){silenciado=false;desmutou++;}},
            window:{speechSynthesis:{},sgaToques:{espera:()=>1850}},playChime(){assert.equal(silenciado,true);},
            SpeechSynthesisUtterance:class{constructor(text){this.text=text;}},
            speechSynthesis:{getVoices:()=>[],speak(v){voz=v;if(resultado==='excecao')throw Error('falha');},cancel(){}},
            setTimeout(fn,ms){timers.push({fn,ms});return timers.length;},clearTimeout(){}};
        vm.createContext(contexto);vm.runInContext(codigo,contexto);
        const promessa=vm.runInContext("falarSenha('Pessoa Teste','Sala 1')",contexto);
        assert.equal(silenciado,true);timers.find(t=>t.ms===1850).fn();
        if(resultado==='fim')voz.onend();
        if(resultado==='erro')voz.onerror();
        if(resultado==='timeout')timers.find(t=>t.ms===20000).fn();
        assert.equal(await promessa,resultado==='fim');
        assert.equal(silenciado,mudo);assert.equal(desmutou,mudo?0:1);
        assert.equal(voz.lang,'pt-BR');assert.equal(voz.rate,.95);
    });
}
