// Controles administrativos e campos, somente sobre fixture local descartável.
async (page) => {
    const base=process.env.SGA_TEST_URL || 'http://127.0.0.1:8086';
    if(!['localhost','127.0.0.1'].includes(new URL(base).hostname)) throw Error('Ambiente local obrigatório.');
    const executablePath=process.env.SGA_PLAYWRIGHT_EXECUTABLE;
    const browser=await page.context().browser().browserType().launch({headless:true,...(executablePath?{executablePath}:{})});
    const resultados=[], erros=[];
    const check=(ok,nome)=>{if(!ok)throw Error(nome);resultados.push(nome);};
    try {
        const p=await browser.newPage();p.setDefaultTimeout(7000);
        p.on('pageerror',e=>erros.push(e.message));
        p.on('dialog',d=>d.accept());
        const ir=async rota=>{const r=await p.goto(base+'/'+rota);check(r.status()===200,'Rota '+rota);};
        const enviar=async botao=>{await Promise.all([p.waitForNavigation(),botao.click()]);};
        const form=acao=>p.locator('form').filter({has:p.locator(`input[name="action"][value="${acao}"]`)}).first();
        await ir('index.php');await p.getByLabel('Usuário',{exact:true}).fill('admin-test');await p.getByLabel('Senha',{exact:true}).fill('Senha-local-teste-2026!');await enviar(p.getByRole('button',{name:'Acessar Painel'}));
        const menus=await p.locator('a[href$=".php"]').evaluateAll(as=>[...new Set(as.map(a=>a.getAttribute('href')))]);
        for(const href of menus){await ir('dashboard.php');const a=p.locator(`a[href="${href}"]`).first();
            if(await a.getAttribute('target')==='_blank') {const [aba]=await Promise.all([p.waitForEvent('popup'),a.click()]);await aba.waitForLoadState();check((await aba.title()).includes('Painel'),'Menu TV abre nova aba');await aba.close();}
            else {await a.click();await p.waitForLoadState();check(!p.url().endsWith('/index.php'),'Menu '+href);}
        }
        await ir('salas.php');await form('cadastrar').locator('[name="nome"]').fill('Sala Teste Controles');await enviar(p.getByRole('button',{name:'Cadastrar Sala',exact:true}));
        const sala=p.locator('tr').filter({hasText:'Sala Teste Controles'});check(await sala.count()===1,'Cadastrar sala');await enviar(sala.getByRole('button',{name:'Excluir',exact:true}));check(await sala.count()===0,'Excluir sala com confirmação');
        await ir('usuarios.php');await p.locator('#form-username').fill('controles-ui');await p.locator('#form-nome').fill('Usuário Controles');await p.locator('#form-password').fill('curta');
        check(!await p.locator('#form-password').evaluate(e=>e.checkValidity()),'Senha curta bloqueada no formulário');
        await p.locator('#form-password').fill('Senha-controles-2026!');await enviar(p.getByRole('button',{name:'Cadastrar Usuário',exact:true}));
        let usuario=p.locator('tr').filter({hasText:'controles-ui'});check(await usuario.count()===1,'Cadastrar usuário');
        await usuario.getByRole('button',{name:'Editar',exact:true}).click();await p.getByRole('button',{name:'Cancelar',exact:true}).click();check(await p.locator('#form-action').inputValue()==='cadastrar','Cancelar edição de usuário');
        await usuario.getByRole('button',{name:'Editar',exact:true}).click();await p.locator('#form-nome').fill('Usuário Controles Alterado');await enviar(p.getByRole('button',{name:'Atualizar Usuário',exact:true}));check(await usuario.innerText().then(t=>t.includes('Alterado')),'Editar usuário sem redefinir senha');
        await p.locator('#busca-usuarios').fill('sem-usuario');check(await usuario.isHidden(),'Busca de usuários');await p.locator('#busca-usuarios').fill('');await enviar(usuario.getByRole('button',{name:'Excluir',exact:true}));check(await usuario.count()===0,'Excluir usuário');
        await ir('recepcao.php');const hoje=await p.locator('#data-filtro-input').inputValue();const agendar=form('agendar_unico');
        await agendar.locator('[name="nome"]').fill('Pessoa Controles');await agendar.locator('[name="processo"]').fill('00000010020264058000');await agendar.locator('[name="cpf"]').fill('52998224725');await agendar.locator('[name="perito"]').fill('Perito Teste A');
        check(await agendar.locator('[name="cpf"]').inputValue()==='529.982.247-25','Máscara de CPF na digitação/colagem');
        check(await agendar.locator('[name="processo"]').inputValue()==='0000001-00.2026.4.05.8000','Processo completo formatado ao sair do campo');
        await agendar.locator('[name="cpf"]').fill('11111111111');check(!await agendar.locator('[name="cpf"]').evaluate(e=>e.checkValidity()),'CPF inválido bloqueado');await agendar.locator('[name="cpf"]').fill('52998224725');
        await enviar(agendar.getByRole('button',{name:'Agendar',exact:true}));let pessoa=p.locator('#table-agendados-body tr').filter({hasText:'Pessoa Controles'});check(await pessoa.count()===1,'Agendamento manual');
        await pessoa.getByRole('button',{name:'Editar agendamento de Pessoa Controles',exact:true}).click();await p.locator('#edit-nome').fill('Pessoa Controles Editada');await enviar(p.getByRole('button',{name:'Salvar Alterações',exact:true}));pessoa=p.locator('#table-agendados-body tr').filter({hasText:'Pessoa Controles Editada'});check(await pessoa.count()===1,'Salvar edição de agendamento');
        await pessoa.getByRole('checkbox').check();check(await pessoa.getByRole('checkbox').isChecked(),'Seleção por checkbox');
        await enviar(p.getByRole('button',{name:'Confirmar Selecionados',exact:false}));
        let presente=p.locator('tr').filter({hasText:'Pessoa Controles Editada'});check(await presente.getByRole('button',{name:'Desfazer presença',exact:true}).isVisible(),'Confirmar presença em lote');
        await enviar(presente.getByRole('button',{name:'Desfazer presença',exact:true}));check(await p.locator('#table-agendados-body tr').filter({hasText:'Pessoa Controles Editada'}).count()===1,'Desfazer presença antes de chamada');
        await ir('gerenciar_pauta.php');await p.locator('#mover-tipo').selectOption('dia');check(await p.locator('#mover-origem-dia').isVisible(),'Mover por dia');await p.locator('#mover-tipo').selectOption('mes');check(await p.locator('#mover-origem-mes').isVisible(),'Mover por mês');await p.locator('#mover-tipo').selectOption('selecionados');
        await p.locator('#perito-tipo').selectOption('dia');check(await p.locator('#perito-origem-dia').isVisible(),'Substituir perito por dia');await p.locator('#perito-tipo').selectOption('selecionados');
        await p.locator('#excluir-tipo').selectOption('mes');check(await p.locator('#excluir-origem-mes').isVisible(),'Cancelar por mês');await p.locator('#excluir-tipo').selectOption('dia');
        const linha=p.locator('tr').filter({hasText:'Pessoa Controles Editada'});await linha.getByRole('checkbox').check();check(await form('mover_pauta').locator('[name="selecionados[]"]').count()===1,'Seleção sincroniza formulário de transferência');
        await form('alterar_perito_lote').locator('[name="novo_perito"]').fill('Perito Teste B');await enviar(form('alterar_perito_lote').getByRole('button',{name:'Substituir Perito',exact:true}));check((await linha.innerText()).includes('Perito Teste B'),'Substituição de perito selecionado');
        await linha.getByRole('checkbox').check();await form('mover_pauta').locator('[name="nova_data"]').fill('2031-01-15');await enviar(form('mover_pauta').getByRole('button',{name:'Transferir Agendamentos',exact:true}));
        await p.goto(base+'/gerenciar_pauta.php?data_busca=2031-01-15');check(await linha.count()===1,'Transferência para outra data');await enviar(linha.getByTitle('Cancelar agendamento'));check((await linha.innerText()).includes('Cancelado'),'Cancelamento individual preserva registro');
        check(await linha.getByRole('button').count()===0,'Histórico cancelado sem ações incompatíveis');await linha.getByRole('checkbox').check();check(await p.locator('#btn-chegada-lote').isDisabled() && await p.locator('#btn-excluir-lote').isDisabled(),'Lote não oferece ação em cancelado');
        await ir('gerenciar_pauta.php');await enviar(form('bloquear_pauta').getByRole('button'));check(await form('liberar_pauta').count()===1,'Bloquear pauta');await enviar(form('liberar_pauta').getByRole('button'));check(await form('bloquear_pauta').count()===1,'Homologar pauta');
        await Promise.all([p.waitForNavigation(),p.locator('#filtro-tipo-busca').selectOption('mes')]);check(await p.locator('[name="mes_busca"]').isVisible(),'Filtro mensal de pautas');await Promise.all([p.waitForNavigation(),p.locator('#filtro-tipo-busca').selectOption('dia')]);await enviar(p.getByRole('button',{name:'Hoje',exact:true}));
        await ir('recepcao.php');const yt=form('set_yt_url');await yt.locator('[name="yt_url"]').fill('https://www.youtube.com/playlist?list=PLinstitucional123');await enviar(yt.getByRole('button',{name:'Atualizar conteúdo',exact:true}));
        const api=await (await p.request.get(base+'/api_senhas.php?apos=0')).json();check(api.playlist_id==='PLinstitucional123','Salvar playlist e propagar pela API');
        await yt.locator('[name="yt_url"]').fill('https://www.youtube.com/watch?v=M7lc1UVf-VE');await enviar(yt.getByRole('button',{name:'Atualizar conteúdo',exact:true}));check((await (await p.request.get(base+'/api_senhas.php?apos=0')).json()).video_id==='M7lc1UVf-VE','Retornar para vídeo único');
        await ir('atendente.php');await Promise.all([p.waitForNavigation(),p.locator('[name="perito_selecionado"]').selectOption('Perito Teste A')]);await form('chamar_avulsa').locator('[name="nome_avulso"]').fill('Pessoa Chamada Manual');await enviar(form('chamar_avulsa').getByRole('button',{name:'Chamar Manual',exact:true}));check(await form('chamar_novamente').count()===1,'Chamada manual');
        await enviar(form('chamar_novamente').getByRole('button'));check(await p.getByText('Pessoa Chamada Manual',{exact:true}).count()>0,'Rechamada');await enviar(form('nao_compareceu').getByRole('button'));check(await form('iniciar_atendimento').count()===0,'Não compareceu devolve à fila');
        const chamada=p.locator('form').filter({has:p.locator('input[name="action"][value="chamar_especifico"]')}).filter({has:p.getByRole('button')}).first();
        if(await chamada.count()){await enviar(chamada.getByRole('button'));await enviar(form('iniciar_atendimento').getByRole('button'));await enviar(form('concluir').getByRole('button'));check(true,'Chamada específica, início e conclusão');}
        await ir('auditoria.php');await p.locator('[name="busca"]').fill('Pessoa');await enviar(p.getByRole('button',{name:'aplicar filtros de auditoria',exact:false}));check(p.url().includes('busca=Pessoa'),'Filtros de auditoria');
        await p.locator('a[href*="aba=logs"]').first().click();await p.waitForLoadState();check(p.url().includes('aba=logs'),'Aba de logs');
        const exportar=p.locator('a[href*="action=exportar"]').first();const [download]=await Promise.all([p.waitForEvent('download'),exportar.click()]);check((await download.suggestedFilename()).endsWith('.csv'),'Exportar CSV por clique');
        await ir('ajuda.php');for(const d of await p.locator('details').all()){await d.locator('summary').click();check(true,'Seção expansível de ajuda');}
        check(erros.length===0,'Nenhum erro JavaScript nos controles');
        return {aprovados:resultados.length,resultados,erros};
    } catch(e) {throw Error(e.message+' | Últimas verificações: '+resultados.slice(-4).join('; '));}
    finally {await browser.close();}
}
