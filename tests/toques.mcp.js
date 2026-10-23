// Valida os toques no navegador sem ativar ou modificar a voz.
async (page) => {
    const base=process.env.SGA_TEST_URL || 'http://127.0.0.1:8086';
    if(!['localhost','127.0.0.1'].includes(new URL(base).hostname))throw new Error('Somente homologação local.');
    const executablePath=process.env.SGA_PLAYWRIGHT_EXECUTABLE;
    const browser=await page.context().browser().browserType().launch({headless:true,...(executablePath?{executablePath}:{})});
    const resultados=[];const check=(ok,nome)=>{if(!ok)throw new Error(nome);resultados.push(nome);};
    try {
        const context=await browser.newContext({viewport:{width:1366,height:768}});
        await context.addInitScript(()=>{
            window.__falas=0;window.__osc=0;window.__pendentes=0;
            const speak=speechSynthesis.speak.bind(speechSynthesis);
            speechSynthesis.speak=u=>{window.__falas++;return speak(u);};
            const Original=window.AudioContext;
            window.AudioContext=class extends Original {
                createOscillator(){
                    const osc=super.createOscillator();window.__osc++;
                    const start=osc.start.bind(osc);
                    osc.start=(...args)=>{window.__pendentes++;return start(...args);};
                    osc.addEventListener('ended',()=>window.__pendentes--);return osc;
                }
            };
        });
        const p=await context.newPage();p.setDefaultTimeout(6000);
        await p.route('https://www.youtube.com/**',route=>route.abort());
        await p.goto(base+'/painel.php');
        const select=p.getByLabel('Toque de alerta',{exact:true});
        check(await select.locator('option').count()===6,'Seis toques incluídos');
        check(await select.inputValue()==='original','Original preservado como padrão');
        const volume=p.getByLabel('Volume do toque',{exact:true});
        await volume.focus();await volume.press('Home');for(let n=0;n<12;n++)await volume.press('ArrowRight');
        check(await volume.inputValue()==='60','Volume do toque ajustável pelo teclado');
        for(const id of ['original','suave','dingdong','bipes','notas','cristal']) {
            await select.selectOption(id);
            const antes=await p.evaluate(()=>window.__osc);
            await p.getByRole('button',{name:'Ouvir só o toque',exact:true}).click();
            check(await p.evaluate(()=>window.__osc)>antes,`Prévia reproduz ${id}`);
            await p.waitForFunction(()=>window.__pendentes===0);
        }
        check(await p.evaluate(()=>window.__falas)===0,'Ouvir os seis toques não dispara voz');
        await p.reload();
        check(await select.inputValue()==='cristal' && await volume.inputValue()==='60','Seleção e volume preservados após recarregar');
        await volume.focus();await volume.press('Home');
        const antes=await p.evaluate(()=>window.__osc);
        await p.getByRole('button',{name:'Ouvir só o toque',exact:true}).click();
        check(await p.evaluate(()=>window.__osc)===antes,'Volume zero não toca alerta');
        await select.selectOption('suave');for(let n=0;n<12;n++)await volume.press('ArrowRight');
        await p.screenshot({path:'.playwright-mcp/toques-tv.png',fullPage:false});
        const bloqueado=await browser.newContext();
        await bloqueado.addInitScript(()=>Object.defineProperty(window,'localStorage',{get(){throw new Error('bloqueado');}}));
        const b=await bloqueado.newPage();await b.goto(base+'/painel.php?token=local-tv-test-token');
        await b.getByLabel('Toque de alerta',{exact:true}).selectOption('dingdong');
        check((await b.locator('#toque-status').innerText()).includes('nesta sessão'),'Storage bloqueado mantém escolha em memória com aviso');
        return {aprovados:resultados.length,resultados};
    } finally {await browser.close();}
}
