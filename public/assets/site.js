const form=document.querySelector('#callback');
function goal(name){if(window.bannerMetrika && typeof ym==='function')ym(window.bannerMetrika,'reachGoal',name)}
document.querySelectorAll('[data-goal]').forEach(a=>a.addEventListener('click',()=>goal(a.dataset.goal)));
if(form){
 const status=form.querySelector('[role=status]'),button=form.querySelector('button');
 form.addEventListener('submit',async e=>{e.preventDefault();const phone=form.elements.phone;const digits=phone.value.replace(/\D/g,'');phone.setCustomValidity((/^[0-9+()\s-]{1,40}$/.test(phone.value)&&(/^[78][3489]\d{9}$/.test(digits)||/^[3489]\d{9}$/.test(digits)))?'':'Введите российский номер телефона.');if(!form.reportValidity())return;
  form.querySelectorAll('.error').forEach(x=>x.textContent='');button.disabled=true;status.textContent='Отправляем…';
  const data=Object.fromEntries(new FormData(form));data.consent=form.elements.consent.checked;data.page=location.pathname;
  data.utm={};const params=new URLSearchParams(location.search);['utm_source','utm_medium','utm_campaign','utm_content','utm_term'].forEach(k=>{if(params.has(k))data.utm[k]=params.get(k).slice(0,150)});
  try{const response=await fetch('/api/callback.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});const result=await response.json();
   status.textContent=result.message;
   if(result.errors)Object.entries(result.errors).forEach(([key,value])=>{const el=form.querySelector(`[data-error="${key}"]`);if(el)el.textContent=value});
   if(response.ok&&!result.demo){form.reset();goal('lead_saved')}
  }catch{status.textContent='Не удалось отправить заявку. Повторите попытку позже.'}
  finally{button.disabled=false}
 });
}

if(form)form.elements.phone.addEventListener('input',()=>form.elements.phone.setCustomValidity(''));
