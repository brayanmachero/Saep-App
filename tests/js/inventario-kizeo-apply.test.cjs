const test = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const source = fs.readFileSync('public/js/inventario-kizeo-apply.js', 'utf8');

function setup(fetch) {
    let listener;
    const result = {hidden:true,textContent:''};
    const submit = {disabled:false,innerHTML:'Aplicar',parentElement:{before(){}}};
    const status = {};
    const controls = [{disabled:false}];
    const card = {querySelector:()=>status,querySelectorAll:()=>[]};
    card.querySelector = selector => selector.includes('checkbox') ? null : status;
    const form = {action:'/aplicar',querySelector:s=>s.includes('result')?result:submit,
        addEventListener:(name,fn)=>listener=fn, setAttribute(){}, removeAttribute(){},
        closest:()=>card, querySelectorAll:()=>controls};
    const events=[];
    vm.runInNewContext(source, {document:{addEventListener:(name,fn)=>fn(),querySelectorAll:()=>[form],dispatchEvent:e=>events.push(e)},
        FormData:class {}, CustomEvent:class {constructor(name,data){this.detail=data.detail;}},fetch});
    return {result,submit,status,controls,events,send:()=>listener({preventDefault(){}})};
}
const response = (ok,payload) => ({ok,status:ok?200:422,redirected:false,headers:{get:()=> 'application/json'},json:async()=>payload});
test('validation failure stays inline and allows a corrected retry', async()=>{
    const ui=setup(async()=>response(false,{errors:{cantidad:['Stock insuficiente']},stocks:{1:0}}));
    await ui.send();
    assert.equal(ui.result.textContent,'Stock insuficiente');
    assert.equal(ui.submit.disabled,false);
    assert.equal(ui.controls[0].disabled,false);
    assert.equal(ui.events[0].detail[1],0);
});
test('success marks applied and blocks duplicate submissions', async()=>{
    let calls=0;
    const ui=setup(async()=>{calls++;return response(true,{message:'Aplicada',stocks:{1:8}});});
    await ui.send(); await ui.send();
    assert.equal(calls,1); assert.equal(ui.status.textContent,'Salida descontada');
    assert.equal(ui.submit.disabled,true); assert.equal(ui.controls[0].disabled,true);
});
test('double click in flight sends once; network uncertainty does not auto retry', async()=>{
    let reject,calls=0;
    const ui=setup(()=>{calls++;return new Promise((resolve,fail)=>reject=fail);});
    const first=ui.send(); await ui.send(); reject(new Error('offline')); await first;
    assert.equal(calls,1); assert.match(ui.result.textContent,/Puede haberse completado/);
});
