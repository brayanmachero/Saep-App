(() => {
    'use strict';
    const root = document.querySelector('[data-vehicle-sketch]');
    if (!root) return;
    const canvas = root.querySelector('[data-sketch-canvas]'), ctx = canvas.getContext('2d');
    const input = root.querySelector('[data-sketch-value]'), status = root.querySelector('[data-sketch-status]');
    const undo = root.querySelector('[data-sketch-undo]'), clear = root.querySelector('[data-sketch-clear]');
    const names = {izquierdo:'Lado izquierdo', derecho:'Lado derecho', frente:'Frente', trasera:'Parte trasera', superior:'Vista superior'};
    const views = Object.keys(names), marks = Object.fromEntries(views.map(view => [view, []]));
    let active = views[0], gesture = null;
    const path = (c, d, fill = '#fff') => { const p = new Path2D(d); if (fill) { c.fillStyle = fill; c.fill(p); } c.stroke(p); };
    const box = (c, x,y,w,h,r=8,fill='#fff') => { c.beginPath(); c.roundRect(x,y,w,h,r); c.fillStyle=fill; c.fill(); c.stroke(); };
    const wheel = (c,x,y) => { c.fillStyle='#edf1f5'; c.beginPath(); c.arc(x,y,53,0,Math.PI*2); c.fill(); c.stroke(); c.beginPath(); c.arc(x,y,29,0,Math.PI*2); c.stroke(); c.beginPath(); c.arc(x,y,7,0,Math.PI*2); c.stroke(); };
    function guide(c, view) {
        c.save(); c.fillStyle='#fff'; c.fillRect(0,0,900,500);
        c.fillStyle='#253650'; c.font='600 23px Arial'; c.textAlign='center'; c.fillText(names[view],450,42);
        c.strokeStyle='#53647b'; c.lineWidth=3; c.lineJoin='round'; c.lineCap='round';
        if (view === 'izquierdo' || view === 'derecho') {
            if (view === 'derecho') { c.translate(900,0); c.scale(-1,1); }
            wheel(c,230,358); wheel(c,685,358);
            path(c,'M90 337 L90 183 Q92 151 126 151 L517 151 Q553 151 577 181 L637 248 L765 267 Q803 277 807 312 L807 351 L746 351 Q743 292 685 292 Q626 292 624 351 L290 351 Q287 292 230 292 Q171 292 169 351 L103 351 Q90 351 90 337 Z');
            path(c,'M431 173 L510 173 Q537 173 557 196 L598 245 L431 245 Z','#e8eff5');
            box(c,119,175,278,91,9,'#e8eff5');
            path(c,'M416 158 L416 341 M431 255 L602 255 L611 336 M301 276 L301 341 M111 279 L398 279 M117 327 L165 327 M300 327 L603 327 M747 327 L789 327',null);
            box(c,446,267,30,6,3,'#53647b'); box(c,323,279,28,6,3,'#53647b');
            path(c,'M644 258 L667 255 L673 268 L646 273 Z','#e8eff5');
            box(c,776,283,22,28,4,'#edf1f5'); box(c,94,269,13,35,3,'#edf1f5');
        } else if (view === 'frente' || view === 'trasera') {
            box(c,274,353,35,70,10,'#edf1f5'); box(c,591,353,35,70,10,'#edf1f5');
            path(c,'M283 370 L283 225 L305 135 Q310 111 338 111 L562 111 Q590 111 595 135 L617 225 L617 370 Q617 390 594 390 L306 390 Q283 390 283 370 Z');
            box(c,252,213,29,39,6,'#edf1f5'); box(c,619,213,29,39,6,'#edf1f5');
            if (view === 'frente') {
                path(c,'M319 142 Q321 134 337 134 L563 134 Q578 134 581 145 L596 225 L304 225 Z','#e8eff5');
                path(c,'M305 245 Q450 263 595 245 M328 215 L414 203 M468 213 L554 203',null);
                box(c,301,285,63,39,9,'#e8eff5'); box(c,536,285,63,39,9,'#e8eff5');
                box(c,380,288,140,40,7,'#edf1f5'); path(c,'M390 299 L510 299 M390 310 L510 310 M300 348 L600 348',null);
            } else {
                box(c,310,140,128,107,9,'#e8eff5'); box(c,462,140,128,107,9,'#e8eff5');
                path(c,'M450 121 L450 363 M308 268 L592 268',null);
                box(c,296,272,18,65,4,'#edf1f5'); box(c,586,272,18,65,4,'#edf1f5'); box(c,467,276,24,6,3,'#53647b');
                path(c,'M306 358 L594 358',null);
            }
            box(c,407,344,86,23,3,'#f3f5f8');
        } else {
            box(c,297,161,24,65,5,'#edf1f5'); box(c,579,161,24,65,5,'#edf1f5');
            box(c,297,337,24,65,5,'#edf1f5'); box(c,579,337,24,65,5,'#edf1f5');
            path(c,'M320 410 L320 145 Q320 91 367 85 L533 85 Q580 91 580 145 L580 410 Q580 438 551 438 L349 438 Q320 438 320 410 Z');
            path(c,'M340 150 Q450 130 560 150 L546 208 L354 208 Z','#e8eff5');
            box(c,358,222,184,151,12,'#f6f8fa');
            path(c,'M339 169 L339 359 L350 370 L350 211 Z','#e8eff5');
            path(c,'M561 169 L561 359 L550 370 L550 211 Z','#e8eff5');
            path(c,'M352 388 L548 388 L557 416 L343 416 Z','#e8eff5');
            path(c,'M340 115 Q450 101 560 115 M336 425 L564 425',null);
            c.fillStyle='#68758a'; c.font='15px Arial'; c.fillText('Frente ↑',450,75);
        }
        c.restore();
    }
    function circle(c, mark, index) {
        c.save(); c.strokeStyle='#d92d20'; c.lineWidth=5; c.fillStyle='rgba(217,45,32,.045)';
        c.beginPath(); c.arc(mark.x,mark.y,mark.r,0,Math.PI*2); c.fill(); c.stroke();
        c.fillStyle='#d92d20'; c.beginPath(); c.arc(mark.x+mark.r*.7,mark.y-mark.r*.7,14,0,Math.PI*2); c.fill();
        c.fillStyle='#fff'; c.font='bold 17px Arial'; c.textAlign='center'; c.textBaseline='middle'; c.fillText(String(index+1),mark.x+mark.r*.7,mark.y-mark.r*.7); c.restore();
    }
    function draw() { guide(ctx,active); marks[active].forEach((mark,i)=>circle(ctx,mark,i)); if(gesture)circle(ctx,gesture,marks[active].length); }
    function update() {
        draw(); const count = views.reduce((sum,v)=>sum+marks[v].length,0);
        if (count) {
            // Store all five views in one immutable image, not just the selected tab.
            const result=document.createElement('canvas'); result.width=1500; result.height=600;
            const out=result.getContext('2d'); out.fillStyle='#fff';out.fillRect(0,0,1500,600);
            views.forEach((view,i)=>{out.save();out.translate((i%3)*500,Math.floor(i/3)*300+11);out.scale(500/900,500/900);guide(out,view);marks[view].forEach((mark,j)=>circle(out,mark,j));out.restore();});
            out.fillStyle='#53647b';out.font='18px Arial';out.fillText('SAEP · Inspección de vehículo',1030,380);out.font='15px Arial';out.fillText('Círculos rojos: detalles observados',1030,415);out.fillText('Guía esquemática · no a escala',1030,445);
            input.value=result.toDataURL('image/png');
        } else input.value='';
        status.textContent=count ? `${count} ${count===1?'zona marcada':'zonas marcadas'} en total. Se guardarán las cinco vistas.` : 'Sin marcas nuevas. Si existe un croquis guardado, se conserva.';
        root.querySelectorAll('[data-sketch-count]').forEach(el=>{el.textContent=marks[el.dataset.sketchCount].length || '';});
        undo.disabled=!marks[active].length; clear.disabled=!marks[active].length;
        input.dispatchEvent(new Event('change',{bubbles:true}));
    }
    const point = e => {const r=canvas.getBoundingClientRect();return {x:Math.max(20,Math.min(880,(e.clientX-r.left)*900/r.width)),y:Math.max(65,Math.min(470,(e.clientY-r.top)*500/r.height))};};
    canvas.addEventListener('pointerdown',e=>{if(e.button!==0 || gesture)return;e.preventDefault();if(views.reduce((n,v)=>n+marks[v].length,0)>=30){status.textContent='Máximo 30 marcas. Usa las observaciones para detallar.';return;}const p=point(e);gesture={...p,r:42,pointer:e.pointerId};canvas.setPointerCapture(e.pointerId);draw();});
    canvas.addEventListener('pointermove',e=>{if(!gesture || e.pointerId!==gesture.pointer)return;const p=point(e),distance=Math.hypot(p.x-gesture.x,p.y-gesture.y);if(distance>10)gesture.r=Math.max(22,Math.min(150,distance));draw();});
    canvas.addEventListener('pointerup',e=>{if(!gesture || e.pointerId!==gesture.pointer)return;marks[active].push({x:gesture.x,y:gesture.y,r:gesture.r});gesture=null;update();});
    canvas.addEventListener('pointercancel',()=>{gesture=null;draw();});
    root.querySelectorAll('[data-sketch-view]').forEach(button=>button.addEventListener('click',()=>{active=button.dataset.sketchView;gesture=null;root.querySelectorAll('[data-sketch-view]').forEach(b=>b.setAttribute('aria-pressed',String(b===button)));draw();undo.disabled=!marks[active].length;clear.disabled=!marks[active].length;}));
    undo.addEventListener('click',()=>{marks[active].pop();update();});
    clear.addEventListener('click',()=>{marks[active]=[];update();});
    draw();
})();
