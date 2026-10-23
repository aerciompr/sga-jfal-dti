async (page) => {
    const base=process.env.SGA_TEST_URL || 'http://127.0.0.1:8086';
    if(!['localhost','127.0.0.1'].includes(new URL(base).hostname))throw Error('Somente fixture local');
    const executablePath=process.env.SGA_PLAYWRIGHT_EXECUTABLE;
    const b=await page.context().browser().browserType().launch({headless:true,...(executablePath?{executablePath}:{})});
    const resultados=[];const check=(ok,nome)=>{if(!ok)throw Error(nome);resultados.push(nome);};
    try {
        const p=await b.newPage();p.setDefaultTimeout(7000);p.on('dialog',d=>d.accept());
        const enviar=async btn=>{await Promise.all([p.waitForNavigation(),btn.click()]);};
        const form=acao=>p.locator('form').filter({has:p.locator(`input[name=action][value="${acao}"]`)}).first();
        const agendar=async(nome,data='')=>{
            await p.goto(base+'/recepcao.php'+(data?'?data_filtro='+data:''));const f=form('agendar_unico');
            await f.locator('[name=nome]').fill(nome);await f.locator('[name=processo]').fill(nome);await f.locator('[name=perito]').fill('Perito Teste A');await enviar(f.getByRole('button',{name:'Agendar',exact:true}));
        };
        await p.goto(base+'/index.php');await p.getByLabel('Usuário',{exact:true}).fill('admin-test');await p.getByLabel('Senha',{exact:true}).fill('Senha-local-teste-2026!');await enviar(p.getByRole('button',{name:'Acessar Painel'}));
        await agendar('LOTE-PRIMEIRO');await p.goto(base+'/gerenciar_pauta.php');let linha=p.locator('tr').filter({hasText:'LOTE-PRIMEIRO'});
        await enviar(linha.getByTitle('Confirmar Presença'));check((await linha.innerText()).includes('Na Fila'),'Presença individual em Pautas');
        await linha.getByRole('checkbox').check();await enviar(p.locator('#btn-cancelar-lote'));check((await linha.innerText()).includes('Agendado'),'Desfazer presença em lote');
        await linha.getByRole('checkbox').check();await enviar(p.locator('#btn-chegada-lote'));check((await linha.innerText()).includes('Na Fila'),'Confirmar presença em lote em Pautas');
        await enviar(linha.getByTitle('Retornar para Agendados'));check((await linha.innerText()).includes('Agendado'),'Desfazer presença individual em Pautas');
        await p.goto(base+'/recepcao.php');linha=p.locator('#table-agendados-body tr').filter({hasText:'LOTE-PRIMEIRO'});await linha.getByRole('checkbox').check();await enviar(p.getByRole('button',{name:'Cancelar selecionados',exact:false}));check(await linha.count()===0,'Cancelar selecionados pela Recepção');
        await agendar('LOTE-LIMPAR');await enviar(p.getByRole('button',{name:'Cancelar pendentes',exact:true}));check(await p.locator('#table-agendados-body tr').filter({hasText:'LOTE-LIMPAR'}).count()===0,'Cancelar pendentes do dia');
        await agendar('LOTE-FUTURO','2034-01-10');await p.goto(base+'/gerenciar_pauta.php?data_busca=2034-01-10');
        await p.locator('#perito-tipo').selectOption('dia');await form('alterar_perito_lote').locator('[name=perito_data_origem]').fill('2034-01-10');await form('alterar_perito_lote').locator('[name=novo_perito]').fill('Perito Teste B');await enviar(form('alterar_perito_lote').getByRole('button'));
        check((await p.locator('tr').filter({hasText:'LOTE-FUTURO'}).innerText()).includes('Perito Teste B'),'Substituir perito por dia');
        await p.locator('#mover-tipo').selectOption('dia');await form('mover_pauta').locator('[name=mover_data_origem]').fill('2034-01-10');await form('mover_pauta').locator('[name=nova_data]').fill('2034-01-11');await enviar(form('mover_pauta').getByRole('button'));
        await p.goto(base+'/gerenciar_pauta.php?data_busca=2034-01-11');check(await p.locator('tr').filter({hasText:'LOTE-FUTURO'}).count()===1,'Transferência por dia');
        await form('excluir_periodo').locator('[name=data_alvo]').fill('2034-01-11');await enviar(form('excluir_periodo').getByRole('button'));check((await p.locator('tr').filter({hasText:'LOTE-FUTURO'}).innerText()).includes('Cancelado'),'Cancelar período por dia');
        await agendar('LOTE-MENSAL','2034-02-28');await p.goto(base+'/gerenciar_pauta.php?data_busca=2034-02-28');await p.locator('#mover-tipo').selectOption('mes');await form('mover_pauta').locator('[name=mover_mes_origem]').fill('2034-02');await form('mover_pauta').locator('[name=nova_data]').fill('2034-03');await enviar(form('mover_pauta').getByRole('button'));
        await p.goto(base+'/gerenciar_pauta.php?data_busca=2034-03-28');check(await p.locator('tr').filter({hasText:'LOTE-MENSAL'}).count()===1,'Transferência mensal preserva dia');
        await p.locator('#excluir-tipo').selectOption('mes');await form('excluir_periodo').locator('[name=mes_alvo]').fill('2034-03');await enviar(form('excluir_periodo').getByRole('button'));check((await p.locator('tr').filter({hasText:'LOTE-MENSAL'}).innerText()).includes('Cancelado'),'Cancelar período mensal');
        return {aprovados:resultados.length,resultados};
    } catch(e){throw Error(e.message+' | Últimas verificações: '+resultados.slice(-3).join('; '));}
    finally {await b.close();}
}
