document.addEventListener('DOMContentLoaded',function(){
  var button=document.getElementById('menu-toggle');
  if(button){button.addEventListener('click',function(){document.querySelector('.sidebar')?.classList.toggle('open')});}
  document.querySelectorAll('form[data-confirm]').forEach(function(form){
    form.addEventListener('submit',function(evt){if(!window.confirm(form.dataset.confirm||'Are you sure?'))evt.preventDefault();});
  });
});
