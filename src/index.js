import { TWOP } from './twop';
import './main.sass';
const twop = new TWOP(process.env.TWOP_FETCH_HOST);
window.onload = twop.resizeClasses;
window.onresize = twop.resizeClasses;
twop.searchButton.addEventListener('click', twop.doSearch, event);
twop.input.addEventListener('input', twop.validUrl);
twop.clearButton.addEventListener('click', function(){twop.resetForm(true, true)});
//console.log(twop);
document.querySelector('#up').addEventListener('click', function() {
  this.classList.add('is-hidden');
  document.querySelector('#down').classList.remove('is-hidden');
  document.querySelector('#intro').classList.add('is-hidden');
});
document.querySelector('#down').addEventListener('click', function() {
  this.classList.add('is-hidden');
  document.querySelector('#up').classList.remove('is-hidden');
  document.querySelector('#intro').classList.remove('is-hidden');
});
