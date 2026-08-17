const date=document.querySelector('#date'),local=document.querySelector('#local'),box=document.querySelector('#availability');
const loginLoader=document.querySelector('.login-loader');
if(loginLoader)setTimeout(()=>{loginLoader.classList.add('leaving');document.body.classList.remove('login-loading');setTimeout(()=>loginLoader.remove(),450)},3000);
const menuButton=document.querySelector('.menu-button'),mainNav=document.querySelector('#main-nav');
const closeMenu=()=>{mainNav?.classList.remove('open');menuButton?.setAttribute('aria-expanded','false');menuButton?.setAttribute('aria-label','Abrir menu');};
menuButton?.addEventListener('click',()=>{const open=!mainNav.classList.contains('open');mainNav.classList.toggle('open',open);menuButton.setAttribute('aria-expanded',String(open));menuButton.setAttribute('aria-label',open?'Fechar menu':'Abrir menu');});
mainNav?.querySelectorAll('a').forEach(link=>link.addEventListener('click',closeMenu));
document.addEventListener('keydown',event=>{if(event.key==='Escape')closeMenu();});
window.addEventListener('resize',()=>{if(window.innerWidth>800)closeMenu();});
document.body.classList.add('enhanced-ui');

// Tema visual persistente.
const themeToggle=document.querySelector('.theme-toggle');
const applyTheme=theme=>{document.documentElement.dataset.theme=theme;themeToggle?.setAttribute('aria-label',theme==='dark'?'Ativar tema claro':'Ativar tema escuro');};
applyTheme(document.documentElement.dataset.theme||'light');
themeToggle?.addEventListener('click',()=>{const theme=document.documentElement.dataset.theme==='dark'?'light':'dark';applyTheme(theme);try{localStorage.setItem('agenda-theme',theme)}catch(e){}});

// Realça a página atual na navegação.
const currentPage=location.pathname.split('/').pop()||'index.php';
mainNav?.querySelectorAll('a').forEach(link=>{
 const linkPage=new URL(link.href,location.href).pathname.split('/').pop();
 if(linkPage===currentPage){link.classList.add('active');link.setAttribute('aria-current','page');}
});

// Alertas continuam acessíveis, mas podem ser dispensados sem recarregar a página.
document.querySelectorAll('.alert').forEach(alert=>{
 const close=document.createElement('button');close.type='button';close.className='alert-close';close.setAttribute('aria-label','Fechar aviso');close.textContent='×';
 close.addEventListener('click',()=>{alert.classList.add('leaving');setTimeout(()=>alert.remove(),220)});alert.append(close);
 if(alert.classList.contains('success'))setTimeout(()=>{if(alert.isConnected)close.click()},5000);
});

// Marca visualmente os cartões conforme o status exibido.
document.querySelectorAll('.request-card').forEach(card=>{
 const status=[...card.querySelector('.badge')?.classList||[]].find(name=>['enviada','em_analise','alteracao_solicitada','aprovada','rejeitada'].includes(name));
 if(status)card.dataset.status=status;
});

// Feedback de envio evita a impressão de que o botão não respondeu.
document.querySelectorAll('form').forEach(form=>form.addEventListener('submit',event=>{
 if(event.defaultPrevented||!form.checkValidity())return;const button=event.submitter;
 if(button?.classList.contains('btn')){button.classList.add('is-loading');button.setAttribute('aria-busy','true');button.dataset.label=button.textContent;button.textContent='Enviando…';}
}));

// Contadores discretos ajudam no preenchimento de descrições e retornos.
document.querySelectorAll('textarea').forEach(field=>{
 const counter=document.createElement('small');counter.className='field-counter';
 const update=()=>counter.textContent=`${field.value.length} caracteres`;field.insertAdjacentElement('afterend',counter);field.addEventListener('input',update);update();
});

// Mostra imediatamente se a data escolhida atende à antecedência mínima.
const deadlineField=document.querySelector('input[name="dia_ini"]');
if(deadlineField){
 const deadline=document.createElement('div');deadline.className='deadline-hint';deadlineField.insertAdjacentElement('afterend',deadline);
 const updateDeadline=()=>{if(!deadlineField.value){deadline.textContent='';deadline.className='deadline-hint';return;}const selected=new Date(`${deadlineField.value}T00:00:00`),today=new Date();today.setHours(0,0,0,0);const days=Math.round((selected-today)/86400000);deadline.textContent=days>=90?`✓ Prazo atendido: ${days} dias de antecedência.`:`⚠ ${days} dias de antecedência: a solicitação irá para Negadas.`;deadline.className=`deadline-hint ${days>=90?'ok':'warning'}`;};
 deadlineField.addEventListener('change',updateDeadline);updateDeadline();
}

// Entrada suave apenas quando há suporte e animações estão habilitadas.
const animatedItems=document.querySelectorAll('.summary-cards article,.request-card,.timeline article,.form-section,.event-detail');
if('IntersectionObserver' in window&&!matchMedia('(prefers-reduced-motion: reduce)').matches){
 const observer=new IntersectionObserver(entries=>entries.forEach(entry=>{if(entry.isIntersecting){entry.target.classList.add('in-view');observer.unobserve(entry.target)}}),{threshold:.08});
 animatedItems.forEach(item=>{item.classList.add('reveal-item');observer.observe(item)});
}else animatedItems.forEach(item=>item.classList.add('in-view'));
document.querySelectorAll('.cpf-input').forEach(input=>input.addEventListener('input',()=>{const value=input.value.replace(/\D/g,'').slice(0,11);input.value=value.replace(/(\d{3})(\d)/,'$1.$2').replace(/(\d{3})(\d)/,'$1.$2').replace(/(\d{3})(\d{1,2})$/,'$1-$2')}));
const adminRole=document.querySelector('form.admin-form select[name="tipo_user"]');if(adminRole&&!adminRole.querySelector('option[value="convidado"]'))adminRole.prepend(new Option('Convidado','convidado',true,true));
document.querySelectorAll('.user-actions select[name="tipo_user"]').forEach(select=>{if(!select.querySelector('option[value="convidado"]'))select.prepend(new Option('Convidado','convidado'));});
async function availability(){if(!date?.value||!local?.value)return;box.className='availability wide';box.textContent='Consultando agenda...';try{const r=await fetch(`api-disponibilidade.php?data=${encodeURIComponent(date.value)}&local=${encodeURIComponent(local.value)}`),j=await r.json();box.textContent=(j.disponivel?'✓ ':'✕ ')+j.mensagem;box.classList.add(j.disponivel?'ok':'no')}catch(e){box.textContent='Não foi possível consultar agora.'}}
date?.addEventListener('change',availability);local?.addEventListener('change',availability);
const route=document.querySelector('[name="trajeto"]'),routeInfo=document.querySelector('#routeInfo');
route?.addEventListener('input',()=>{const points=route.value.trim().split(/\n/).filter(line=>/^\s*-?\d+(\.\d+)?\s*,\s*-?\d+(\.\d+)?\s*$/.test(line));routeInfo.textContent=points.length>=2?`✓ ${points.length} pontos válidos no trajeto.`:'O trajeto precisa ter ao menos dois pontos válidos.';routeInfo.className='availability wide '+(points.length>=2?'ok':'no')});

const picker=document.querySelector('#routePicker');
if(picker&&window.L){
 const cityBounds=L.latLngBounds([-20.30,-45.06],[-19.98,-44.70]);
 const map=L.map(picker,{maxBounds:cityBounds,maxBoundsViscosity:1}).setView([-20.13917,-44.88806],14);
 L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'© OpenStreetMap'}).addTo(map);
 picker.insertAdjacentHTML('beforebegin','<div class="map-search"><span>⌕</span><input id="placeSearch" type="search" placeholder="Pesquisar rua, praça ou bairro em Divinópolis"><button type="button" class="btn dark" id="placeSearchButton">Localizar</button></div><div id="placeSearchResult" class="map-search-result"></div>');
 const placeInput=document.querySelector('#placeSearch'),placeButton=document.querySelector('#placeSearchButton'),placeResult=document.querySelector('#placeSearchResult');let focusMarker;
 const searchPlace=async()=>{const query=placeInput.value.trim();if(!query)return;placeButton.disabled=true;placeButton.textContent='Buscando...';placeResult.textContent='';try{const params=new URLSearchParams({format:'jsonv2',limit:'5',countrycodes:'br',bounded:'1',viewbox:'-45.06,-19.98,-44.70,-20.30',q:`${query}, Divinópolis, Minas Gerais`});const response=await fetch(`https://nominatim.openstreetmap.org/search?${params}`),results=await response.json();if(!results.length){placeResult.textContent='Local não encontrado em Divinópolis. Tente informar a rua e o bairro.';placeResult.className='map-search-result error';return;}placeResult.className='map-search-result';placeResult.innerHTML=results.map((item,index)=>`<button type="button" data-index="${index}">${item.display_name}</button>`).join('');placeResult.querySelectorAll('button').forEach(button=>button.addEventListener('click',()=>{const item=results[Number(button.dataset.index)],position=[Number(item.lat),Number(item.lon)];if(focusMarker)map.removeLayer(focusMarker);focusMarker=L.circleMarker(position,{radius:10,color:'#e39136',fillColor:'#ffd27f',fillOpacity:.8,weight:3}).addTo(map).bindPopup(`<b>Local encontrado</b><br>${item.display_name}`).openPopup();map.setView(position,17);placeResult.innerHTML='';}));}catch(error){placeResult.textContent='Não foi possível pesquisar agora. Verifique sua internet.';placeResult.className='map-search-result error';}finally{placeButton.disabled=false;placeButton.textContent='Localizar';}};
 placeButton.addEventListener('click',searchPlace);placeInput.addEventListener('keydown',event=>{if(event.key==='Enter'){event.preventDefault();searchPlace();}});
 const ini=document.querySelector('#localIni'),fin=document.querySelector('#localFin'),routeJson=document.querySelector('#routeJson'),status=document.querySelector('#routeStatus');
 let marks=[],line,dots=[];
 const resetButton=document.querySelector('#resetRoute');
 if(resetButton){resetButton.insertAdjacentHTML('beforebegin','<button type="button" class="btn secondary" id="undoRoute">Desfazer último ponto</button> <button type="button" class="btn secondary" id="closeRoute">Fechar circuito 360°</button> ');}
 const coordinate=p=>`${p.lat.toFixed(7)},${p.lng.toFixed(7)}`;
 const clearDrawing=()=>{dots.forEach(d=>map.removeLayer(d));dots=[];if(line)map.removeLayer(line);line=null;routeJson.value='';};
 const updateEndpoints=()=>{ini.value=marks[0]?coordinate(marks[0].getLatLng()):'';fin.value=marks.length>1?coordinate(marks[marks.length-1].getLatLng()):'';marks.forEach((m,i)=>m.setPopupContent(i===0?'Largada':i===marks.length-1?'Chegada':`Ponto de passagem ${i}`));};
 const reset=()=>{marks.forEach(m=>map.removeLayer(m));marks=[];clearDrawing();updateEndpoints();status.textContent='Clique para marcar a largada e depois os pontos do percurso.';status.className='availability';};
 const drawDots=points=>{dots.forEach(d=>map.removeLayer(d));dots=[];const step=Math.max(1,Math.floor(points.length/45));for(let i=0;i<points.length;i+=step)dots.push(L.circleMarker(points[i],{radius:3,color:'#102a43',fillColor:'#fff',fillOpacity:1,weight:2,interactive:false}).addTo(map));};
 const decodePolyline=(encoded,precision=6)=>{let index=0,lat=0,lng=0,points=[],factor=10**precision;while(index<encoded.length){let result=0,shift=0,byte;do{byte=encoded.charCodeAt(index++)-63;result|=(byte&31)<<shift;shift+=5}while(byte>=32);lat+=(result&1)?~(result>>1):(result>>1);result=0;shift=0;do{byte=encoded.charCodeAt(index++)-63;result|=(byte&31)<<shift;shift+=5}while(byte>=32);lng+=(result&1)?~(result>>1):(result>>1);points.push([lat/factor,lng/factor])}return points};
 const calculate=async()=>{if(marks.length<2)return;clearDrawing();status.textContent=`Calculando percurso para corrida com ${marks.length-2} ponto(s) de passagem...`;const locations=marks.map(m=>{const p=m.getLatLng();return{lat:p.lat,lon:p.lng,type:'break'}});const request={locations,costing:'pedestrian',units:'kilometers',directions_options:{units:'kilometers'}};
  try{const response=await fetch(`https://valhalla1.openstreetmap.de/route?json=${encodeURIComponent(JSON.stringify(request))}`),data=await response.json();if(!response.ok||!data.trip?.legs?.length)throw new Error();const points=data.trip.legs.flatMap((leg,i)=>{const decoded=decodePolyline(leg.shape);return i?decoded.slice(1):decoded});line=L.polyline(points,{color:'#24a5b8',weight:6}).addTo(map);drawDots(points);routeJson.value=JSON.stringify(points);status.textContent=`✓ Percurso de pedestre: ${Number(data.trip.summary.length).toFixed(2)} km, ${marks.length} marcações.`;status.className='availability ok';}catch(e){status.textContent='Não foi possível calcular o percurso a pé. Posicione os pontos sobre ruas ou caminhos acessíveis a pedestres.';status.className='availability no';}}
 const addMarker=(latlng,recalculate=true)=>{const marker=L.marker(latlng,{draggable:true}).addTo(map);marks.push(marker);marker.on('dragend',()=>{updateEndpoints();calculate()});updateEndpoints();if(recalculate&&marks.length>1)calculate();};
 map.on('click',e=>addMarker(e.latlng));
 resetButton?.addEventListener('click',reset);
 document.querySelector('#undoRoute')?.addEventListener('click',()=>{const marker=marks.pop();if(marker)map.removeLayer(marker);clearDrawing();updateEndpoints();if(marks.length>1)calculate();});
 document.querySelector('#closeRoute')?.addEventListener('click',()=>{if(marks.length<2)return;addMarker(marks[0].getLatLng());status.textContent='Circuito fechado. O último ponto retorna à largada.';});
 try{const saved=JSON.parse(picker.dataset.route||'null');if(Array.isArray(saved)&&saved.length>1){addMarker(saved[0],false);addMarker(saved[saved.length-1],false);line=L.polyline(saved,{color:'#24a5b8',weight:6}).addTo(map);drawDots(saved);map.fitBounds(line.getBounds(),{padding:[35,35]});status.textContent='Trajeto atual carregado. Arraste os pontos ou clique em “Refazer marcações” para desenhar uma nova rota.';status.className='availability ok';}}catch(e){}
}
const view=document.querySelector('#routeView');
if(view&&window.L){const parse=s=>s.split(',').map(Number),a=parse(view.dataset.start),b=parse(view.dataset.end),map=L.map(view).setView([-20.13917,-44.88806],14);L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'© OpenStreetMap'}).addTo(map);L.marker(a).addTo(map).bindPopup('<b>Largada</b>').openPopup();L.marker(b).addTo(map).bindPopup('<b>Chegada</b>');let points;try{points=JSON.parse(document.querySelector('#savedRoute')?.textContent||'null')}catch(e){}points=Array.isArray(points)&&points.length>1?points:[a,b];const line=L.polyline(points,{color:'#24a5b8',weight:6}).addTo(map);const step=Math.max(1,Math.floor(points.length/45));for(let i=0;i<points.length;i+=step)L.circleMarker(points[i],{radius:3,color:'#102a43',fillColor:'#fff',fillOpacity:1,weight:2}).addTo(map);map.fitBounds(line.getBounds(),{padding:[50,50]});}
const diaIni=document.querySelector('#diaIni'),diaFin=document.querySelector('#diaFin');diaIni?.addEventListener('change',()=>{diaFin.min=diaIni.value;if(!diaFin.value||diaFin.value<diaIni.value)diaFin.value=diaIni.value});
const dateStatus=document.querySelector('#dateStatus');let unavailable=[];if(dateStatus)fetch(`api-datas.php?ignorar=${window.EDIT_REQUEST_ID||0}`).then(r=>r.json()).then(data=>{unavailable=data;const disabled=data.map(x=>({from:x.dia_ini,to:x.dia_fin}));if(window.flatpickr){flatpickr(diaIni,{locale:'pt',dateFormat:'Y-m-d',minDate:'today',disable:disabled});flatpickr(diaFin,{locale:'pt',dateFormat:'Y-m-d',minDate:'today',disable:disabled});}dateStatus.textContent=data.length?`${data.length} período(s) bloqueado(s) aparecem desabilitados no calendário.`:'Todas as datas futuras estão disponíveis.';dateStatus.className='availability '+(data.length?'':'ok')}).catch(()=>dateStatus.textContent='Não foi possível consultar as datas.');
const checkDates=()=>{if(!diaIni?.value||!diaFin?.value)return;const conflict=unavailable.find(x=>x.dia_ini<=diaFin.value&&x.dia_fin>=diaIni.value);if(conflict){dateStatus.textContent=`Data indisponível: ${conflict.nome_evento} ocupa esse período.`;dateStatus.className='availability no';diaFin.value='';}else{dateStatus.textContent='✓ Período disponível.';dateStatus.className='availability ok';}};diaIni?.addEventListener('change',checkDates);diaFin?.addEventListener('change',checkDates);
