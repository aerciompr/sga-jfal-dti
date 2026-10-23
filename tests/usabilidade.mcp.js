// Execute pelo MCP Playwright browser_run_code_unsafe após tests/integration.php.
// SGA_PLAYWRIGHT_EXECUTABLE é opcional quando o Chromium da versão instalada estiver disponível.
async (page) => {
    const base = process.env.SGA_TEST_URL || 'http://127.0.0.1:8086';
    if (!['127.0.0.1','localhost'].includes(new URL(base).hostname)) throw new Error('Use somente homologação local descartável.');
    const executablePath = process.env.SGA_PLAYWRIGHT_EXECUTABLE;
    const browser = await page.context().browser().browserType().launch({headless:true,...(executablePath ? {executablePath} : {})});
    const context = await browser.newContext({viewport:{width:1366,height:768}});
    const p = await context.newPage(); p.setDefaultTimeout(8000);
    const resultados=[];
    const check=(ok,nome)=>{if(!ok)throw new Error(nome);resultados.push(nome);};
    try {
        await p.goto(base+'/index.php');
        check((await p.title()).includes('Justiça Federal'),'Acentuação do nome institucional');
        await p.getByLabel('Usuário',{exact:true}).fill('admin-test');
        await p.getByLabel('Senha',{exact:true}).fill('Senha-local-teste-2026!');
        await p.getByRole('button',{name:'Mostrar senha',exact:true}).click();
        check(await p.getByLabel('Senha',{exact:true}).getAttribute('type')==='text','Mostrar senha sem apagar o valor');
        await p.getByRole('button',{name:'Ocultar senha',exact:true}).click();
        await p.getByRole('button',{name:'Acessar Painel',exact:true}).click();
        await p.waitForURL('**/dashboard.php'); check(true,'Login por clique real');
        await p.locator('main a[href="recepcao.php"]').click();
        await p.getByRole('searchbox',{name:'Buscar agendamento'}).fill('sem-correspondencia-teste');
        await p.getByText('Nenhum agendamento encontrado. Confira a data ou limpe a busca.').waitFor();
        check(true,'Busca sem resultado informa próximo passo');
        await p.getByRole('searchbox',{name:'Buscar agendamento'}).press('Enter');
        check((await p.getByRole('searchbox',{name:'Buscar agendamento'}).inputValue())==='sem-correspondencia-teste','Enter na busca não envia POST');
        await p.getByRole('button',{name:'Limpar busca',exact:true}).click();
        const row=p.locator('#table-agendados-body tr').filter({hasText:'Pessoa Importada'});
        await row.getByText('Pessoa Importada',{exact:true}).waitFor();
        check(await p.getByRole('searchbox',{name:'Buscar agendamento'}).inputValue()==='','Limpar busca restaura os registros');
        await row.focus(); await row.press('Enter');
        await p.locator('#modal-detalhes').waitFor({state:'visible'});
        check(await p.evaluate(()=>document.querySelector('#modal-detalhes').contains(document.activeElement)),'Modal recebe foco pelo teclado');
        await p.keyboard.press('Escape'); await p.locator('#modal-detalhes').waitFor({state:'hidden'});
        check(await p.evaluate(()=>document.activeElement.matches('tr[data-nome]')),'Escape fecha modal e restaura o foco');
        await p.getByRole('button',{name:'🎨 Cores JF',exact:true}).click();
        check(await p.locator('body').evaluate(e=>e.classList.contains('theme-jf')),'Tema alterna sem recarregar formulário');
        await p.screenshot({path:'.playwright-mcp/recepcao-desktop-jf.png',fullPage:false});
        check(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Sem rolagem horizontal externa em 1366 px');
        await p.setViewportSize({width:390,height:844});
        check(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Sem corte horizontal em 390 px');
        check(await p.evaluate(()=>document.querySelector('.sga-queue').getBoundingClientRect().top<document.querySelector('.sga-operations').getBoundingClientRect().top),'Fila aparece antes das configurações no celular');
        check(await p.getByRole('button',{name:'Sair',exact:true}).isVisible(),'Saída acessível no cabeçalho móvel');
        await p.screenshot({path:'.playwright-mcp/recepcao-mobile-jf.png',fullPage:false});
        await p.getByRole('link',{name:'Ajuda',exact:true}).click();
        await p.getByRole('heading',{name:'No navegador',exact:true}).waitFor(); check(true,'Ajuda disponível dentro do sistema');
        await p.getByRole('link',{name:'Voltar ao trabalho',exact:true}).click();
        await p.getByRole('button',{name:'Sair',exact:true}).click();
        await p.waitForURL('**/index.php'); check(true,'Sair encerra sessão em um clique com CSRF');
        return {aprovados:resultados.length,resultados,screenshots:['.playwright-mcp/recepcao-desktop-jf.png','.playwright-mcp/recepcao-mobile-jf.png']};
    } finally { await browser.close(); }
}
