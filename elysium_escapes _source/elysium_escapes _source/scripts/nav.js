function openNav() {
    var Image_Id = document.getElementById('mobile-nav-button');
    if (Image_Id.src.match("../images/hamburger.png")) {
        Image_Id.src = "../images/crossed.png";
    } else {
        Image_Id.src = "../images/hamburger.png";
    }

    if (document.getElementsByClassName("nav")[0].style.left === '100%' || document.getElementsByClassName("nav")[0].style.left === '') {
        document.getElementsByClassName("nav")[0].style.left = '0';
    } else document.getElementsByClassName("nav")[0].style.left = '100%';
}