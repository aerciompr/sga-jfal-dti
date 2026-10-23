async (page) => {
    const base=process.env.SGA_TEST_URL || 'http://127.0.0.1:8086';
    if(!['localhost','127.0.0.1'].includes(new URL(base).hostname))throw new Error('Somente ambiente local.');
    const executablePath=process.env.SGA_PLAYWRIGHT_EXECUTABLE;
    const browser=await page.context().browser().browserType().launch({headless:true,...(executablePath?{executablePath}:{})});
    const context=await browser.newContext();const p=await context.newPage();p.setDefaultTimeout(6000);
    const paginas=['dashboard.php','recepcao.php','atendente.php','gerenciar_pauta.php','salas.php','usuarios.php','auditoria.php','ajuda.php'];
    const resultados=[];const erros=[];p.on('pageerror',e=>erros.push(e.message));
    try {
        await p.goto(base+'/index.php');await p.getByLabel('Usuário',{exact:true}).fill('admin-test');await p.getByLabel('Senha',{exact:true}).fill('Senha-local-teste-2026!');await p.getByRole('button',{name:'Acessar Painel'}).click();await p.waitForURL('**/dashboard.php');
        for(const width of [1366,390]) {
            await p.setViewportSize({width,height:844});
            for(const pagina of paginas) {
                const resposta=await p.goto(base+'/'+pagina);
                if(resposta.status()!==200)throw new Error(`${pagina}: HTTP ${resposta.status()}`);
                const tamanho=await p.evaluate(()=>({viewport:innerWidth,pagina:document.documentElement.scrollWidth}));
                if(tamanho.pagina>tamanho.viewport)throw new Error(`Corte em ${pagina} ${width}px: ${JSON.stringify(tamanho)}`);
                resultados.push(`${pagina} em ${width}px sem corte horizontal`);
            }
        }
        if(erros.length)throw new Error('Erros JavaScript: '+erros.join('; '));
        return {aprovados:resultados.length,resultados,errosJavaScript:erros};
    } finally {await browser.close();}
}
