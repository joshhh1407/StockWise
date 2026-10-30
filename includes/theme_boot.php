<script>
/* applies the saved light/dark mode and the user's chosen theme color before the page paints */
try{
  var d=document.documentElement,t=localStorage.getItem('theme');
  if(!t&&window.matchMedia('(prefers-color-scheme: dark)').matches)t='dark';
  if(t)d.dataset.theme=t;
  var a=localStorage.getItem('accent');
  if(a&&/^#[0-9a-f]{6}$/i.test(a)){
    var n=parseInt(a.slice(1),16),l=(0.2126*(n>>16)+0.7152*((n>>8)&255)+0.0722*(n&255))/255;
    d.style.setProperty('--accent',a);d.style.setProperty('--on-primary',l>0.6?'#0b1410':'#ffffff');
  }
}catch(e){}
</script>
