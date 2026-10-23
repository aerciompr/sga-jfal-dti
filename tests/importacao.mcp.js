// SGA_TEST_XLSX: planilha fictícia local preparada com cabeçalhos do importador.
async (page) => {
    const base=process.env.SGA_TEST_URL || 'http://127.0.0.1:8086';
    if(!['localhost','127.0.0.1'].includes(new URL(base).hostname))throw Error('Ambiente local obrigatório');
    const arquivo=process.env.SGA_TEST_XLSX;
    if(!arquivo)throw Error('Defina SGA_TEST_XLSX com a fixture fictícia');
    const executablePath=process.env.SGA_PLAYWRIGHT_EXECUTABLE;
    const b=await page.context().browser().browserType().launch({headless:true,...(executablePath?{executablePath}:{})});
    const resultados=[];
    const check=(ok,nome)=>{if(!ok)throw Error(nome);resultados.push(nome);};
    try {
        const p=await b.newPage();p.setDefaultTimeout(8000);
        const enviar=async btn=>{await Promise.all([p.waitForNavigation(),btn.click()]);};
        await p.goto(base+'/index.php');await p.getByLabel('Usuário',{exact:true}).fill('admin-test');await p.getByLabel('Senha',{exact:true}).fill('Senha-local-teste-2026!');await enviar(p.getByRole('button',{name:'Acessar Painel'}));
        await p.goto(base+'/recepcao.php');await p.locator('#form-importar input[type=file]').setInputFiles(arquivo);await enviar(p.getByRole('button',{name:'Importar Dados',exact:true}));
        check(p.url().includes('preview_importacao.php'),'Importar pela Recepção abre prévia');
        check((await p.locator('main').innerText()).includes('529.982.247-25'),'CPF mascarado preservado na prévia');
        await enviar(p.getByRole('button',{name:'Cancelar',exact:true}));check(p.url().includes('gerenciar_pauta.php'),'Cancelar prévia retorna à pauta');
        const form=p.locator('form').filter({has:p.locator('input[name=action][value=importar_excel]')});await form.locator('input[type=file]').setInputFiles(arquivo);await enviar(form.getByRole('button',{name:'Iniciar Importação',exact:false}));
        check(p.url().includes('preview_importacao.php'),'Importar por Pautas abre prévia');await enviar(p.getByRole('button',{name:'Confirmar importação',exact:true}));check((await p.locator('main').innerText()).includes('Importação concluída'),'Confirmar importação por clique');
        await p.goto(base+'/recepcao.php?data_filtro=2032-01-15');const linha=p.locator('tr').filter({hasText:'Pessoa Importação Máscara'});check(await linha.count()===1,'Registro importado disponível para consulta');
        await linha.focus();await linha.press('Space');await p.locator('#modal-detalhes').waitFor({state:'visible'});check(true,'Espaço abre detalhes');await p.keyboard.press('Escape');
        await p.goto(base+'/gerenciar_pauta.php');await form.locator('input[type=file]').setInputFiles(arquivo);await enviar(form.getByRole('button',{name:'Iniciar Importação',exact:false}));await enviar(p.getByRole('button',{name:'Confirmar importação',exact:true}));check((await p.locator('main').innerText()).includes('0 novos; 0 atualizados'),'Reimportação com máscara não duplica nem modifica');
        return {aprovados:resultados.length,resultados};
    } finally {await b.close();}
}
