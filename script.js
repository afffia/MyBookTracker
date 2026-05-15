const wrapper = document.querySelector('.wrapper');
const loginlink = document.querySelector('.login-link');
const signuplink = document.querySelector('.signup-link');
const btnpopup = document.querySelector('.btnloginpopup');
const iconclose = document.querySelector('.icon-close');
const profilebox = document.querySelector('.profile-box');
const avatarCircle = document.querySelector('.avatar-circle');
const alertbox = document.querySelector('.alert-box'); 

signuplink?.addEventListener('click', () => wrapper?.classList.add('active'));
loginlink?.addEventListener('click', () => wrapper?.classList.remove('active'));
btnpopup?.addEventListener('click', () => wrapper?.classList.add('active-popup'));
iconclose?.addEventListener('click', () => wrapper?.classList.remove('active-popup'));

if(avatarCircle)avatarCircle.addEventListener('click',()=>
    profilebox.classList.toggle('show'));
if(alertbox){
setTimeout(()=> alertbox.classList.add('show'), 50);
setTimeout(() => {
alertbox.classList.remove('show');
setTimeout(() => alertbox.remove(),1000);
}, 6000);
}




fetch("/api/novel/1")
  .then(res => res.json())
  .then(data => {
    document.querySelector("h1").innerText = data.title;
  });