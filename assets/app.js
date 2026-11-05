'use strict';
document.addEventListener('DOMContentLoaded', () => {
  const opener=document.querySelector('[data-menu]');
  const menu=document.querySelector('[data-menu-panel]');
  function toggleMenu(open){
    if(!opener||!menu)return;
    menu.classList.toggle('open',open);
    opener.setAttribute('aria-expanded',String(open));
    opener.setAttribute('aria-label',open?'Đóng menu':'Mở menu');
  }
  if(opener&&menu){
    opener.addEventListener('click',()=>toggleMenu(!menu.classList.contains('open')));
    menu.querySelectorAll('a').forEach(a=>a.addEventListener('click',()=>toggleMenu(false)));
    document.addEventListener('keydown',e=>{if(e.key==='Escape')toggleMenu(false)});
  }
  const themeToggle=document.querySelector('[data-theme-toggle]');
  function renderTheme(){
    const dark=document.documentElement.dataset.theme==='dark';
    if(themeToggle){themeToggle.textContent=dark?'☀':'◐';themeToggle.setAttribute('aria-label',dark?'Bật giao diện sáng':'Bật giao diện tối')}
  }
  if(themeToggle){themeToggle.addEventListener('click',()=>{
    const value=document.documentElement.dataset.theme==='dark'?'light':'dark';
    document.documentElement.dataset.theme=value;
    try{localStorage.setItem('sr_theme',value)}catch(e){}
    renderTheme();
  });renderTheme()}
  document.querySelectorAll('form[data-confirm]').forEach(form=>{
    form.addEventListener('submit',event=>{
      if(!window.confirm(form.getAttribute('data-confirm')||'Bạn chắc chắn muốn thực hiện?'))event.preventDefault();
    });
  });
  document.querySelectorAll('[data-dismiss]').forEach(button=>{
    button.addEventListener('click',()=>button.closest('.flash')?.remove());
  });
  document.querySelectorAll('[data-autosubmit]').forEach(input=>{
    input.addEventListener('change',()=>input.form?.requestSubmit());
  });
  document.querySelectorAll('input[type="file"]').forEach(input=>{
    input.addEventListener('change',()=>{
      const label=input.closest('.upload-box')?.querySelector('[data-filename]');
      if(label)label.textContent=input.files?.[0]?.name||'Chưa có tệp nào được chọn';
    });
  });
  document.querySelectorAll('[data-share-job]').forEach(button=>{
    button.addEventListener('click',async()=>{
      try{
        await navigator.clipboard.writeText(location.href);
        button.textContent='✓ Đã sao chép liên kết';
      }catch(e){window.prompt('Sao chép liên kết công việc:',location.href)}
    });
  });
  document.querySelectorAll('form').forEach(form=>{
    form.addEventListener('submit',event=>{
      if(event.defaultPrevented||!form.checkValidity()||form.hasAttribute('data-confirm'))return;
      const button=event.submitter||form.querySelector('button[type="submit"],button:not([type])');
      if(button&&button.matches('button')&&!button.disabled){
        button.dataset.originalText=button.textContent;
        button.disabled=true;
        button.textContent='Đang xử lý...';
      }
    });
  });
});
