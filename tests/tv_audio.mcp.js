// Teste nativo de síntese. Ouvir os alto-falantes reais continua sendo necessário.
async (page) => {
    const base=process.env.SGA_TEST_URL || 'http://127.0.0.1:8086';
    if(!['localhost','127.0.0.1'].includes(new URL(base).hostname))throw new Error('Somente homologação local.');
    const executablePath=process.env.SGA_PLAYWRIGHT_EXECUTABLE;
    const browser=await page.context().browser().browserType().launch({headless:true,...(executablePath?{executablePath}:{})});
    try {
        const p=await browser.newPage({viewport:{width:1920,height:1080}});
        await p.goto(base+'/painel.php');
        await p.getByRole('button',{name:'Ativar voz',exact:true}).click();await p.getByRole('button',{name:'Testar som',exact:true}).click();
        await p.waitForFunction(()=>{const t=document.getElementById('audio-status')?.textContent||'';return t.includes('Teste concluído')||t.includes('Não foi possível');},{},{timeout:25000});
        const status=await p.locator('#audio-status').innerText();
        await p.screenshot({path:'.playwright-mcp/tv-teste-som.png',fullPage:false});
        return {sinteseConcluida:status.includes('Teste concluído'),status,conexao:await p.locator('#conexao-tv').innerText(),vozes:await p.evaluate(()=>speechSynthesis.getVoices().map(v=>v.lang))};
    } finally {await browser.close();}
}
