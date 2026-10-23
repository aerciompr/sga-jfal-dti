// Fluxo completo, somente com contas e pauta fictícias de tests/integration.php.
async (page) => {
    const base = process.env.SGA_TEST_URL || 'http://127.0.0.1:8086';
    if (!['127.0.0.1','localhost'].includes(new URL(base).hostname)) throw new Error('Ambiente local obrigatório.');
    const executablePath = process.env.SGA_PLAYWRIGHT_EXECUTABLE;
    const browser=await page.context().browser().browserType().launch({headless:true,...(executablePath?{executablePath}:{})});
    const resultados=[]; const check=(ok,nome)=>{if(!ok)throw new Error(nome);resultados.push(nome);};
    const login=async(login,senha)=>{
        const c=await browser.newContext({viewport:{width:1366,height:768}});const p=await c.newPage();p.setDefaultTimeout(8000);
        await p.goto(base+'/index.php');await p.getByLabel('Usuário',{exact:true}).fill(login);await p.getByLabel('Senha',{exact:true}).fill(senha);await p.getByRole('button',{name:'Acessar Painel'}).click();return p;
    };
    try {
        const recepcao=await login('recepcao-test','Senha-local-teste-2026!');
        await recepcao.waitForURL('**/recepcao.php');check(true,'Recepção entra diretamente na pauta');
        await recepcao.getByRole('button',{name:'Confirmar Chegada',exact:true}).click();
        await recepcao.getByRole('status').filter({hasText:'Presença confirmada: Pessoa Importada. Senha:'}).waitFor();
        check(true,'Check-in informa pessoa e senha gerada');
        const perito=await login('perito-a','Nova-senha-teste-2026!');await perito.waitForURL('**/atendente.php');
        await perito.getByRole('button',{name:'🔊 Chamar Próximo da Fila',exact:true}).click();
        await perito.getByText('Paciente chamado.',{exact:true}).waitFor();
        check(await perito.getByRole('button',{name:'🔊 Chamar Próximo da Fila',exact:true}).isDisabled(),'Próxima chamada desabilitada enquanto há ativo');
        await perito.getByText('Conclua o atendimento atual antes de chamar outra pessoa.',{exact:true}).waitFor();
        await perito.getByRole('button',{name:'🚀 Iniciar Perícia',exact:true}).click();
        await perito.getByText('Perícia iniciada.',{exact:true}).waitFor();check(true,'Perito inicia atendimento por clique');
        await perito.getByRole('button',{name:'✓ Concluir Perícia',exact:true}).click();
        await perito.getByText('Perícia concluída.',{exact:true}).waitFor();check(true,'Perito conclui atendimento por clique');
        check(await perito.getByRole('button',{name:'🔊 Chamar Próximo da Fila',exact:true}).isEnabled(),'Conclusão libera próxima chamada');
        await perito.screenshot({path:'.playwright-mcp/perito-desktop.png',fullPage:false});
        const tvContext=await browser.newContext({viewport:{width:1920,height:1080}});const tv=await tvContext.newPage();
        await tv.goto(base+'/painel.php');
        await tv.getByRole('button',{name:'Ativar voz',exact:true}).waitFor();
        check(!tv.url().includes('token='),'TV abre diretamente na intranet');
        check(await tv.getByRole('button',{name:'Testar som',exact:true}).isVisible(),'Teste de som disponível');
        await tv.getByRole('button',{name:'Tela cheia',exact:true}).click();
        check(await tv.evaluate(()=>!!document.fullscreenElement),'Tela cheia ativada por clique');
        await tv.getByRole('button',{name:'Sair da tela cheia',exact:true}).click();
        check(await tv.evaluate(()=>!document.fullscreenElement),'Saída de tela cheia funciona');
        await tv.screenshot({path:'.playwright-mcp/tv-preparacao.png',fullPage:false});
        return {aprovados:resultados.length,resultados,vozDisponivel:await tv.evaluate(()=>({sintese:'speechSynthesis' in window,portugues:speechSynthesis.getVoices().filter(v=>v.lang.startsWith('pt')).map(v=>v.lang)}))};
    } finally {await browser.close();}
}
