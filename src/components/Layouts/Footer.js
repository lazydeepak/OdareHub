import React from 'react';
import '../Layouts/Footer.css';

function Footer() {
    return (
        <div>
            
            <footer>

        <div className="about">
          <div className="container">
            <div className="row">
              <hr className="offset-md" />
              <div className="col-xs-6 col-sm-3">
                <div className="item d-flex">
                  <i className="bi bi-telephone "/> &nbsp; &nbsp;
                  <h6>24/7 free <br /> <span>support</span></h6>
                </div>
              </div>
              <div className="col-xs-6 col-sm-3">
                <div className="item d-flex">
                  <i className="bi bi-star" /> &nbsp; &nbsp;
                  <h6>Low price <br /> <span>guarantee</span></h6>
                </div>
              </div>
              <div className="col-xs-6 col-sm-3">
                <div className="item d-flex">
                  <i className="bi bi-gear" /> &nbsp; &nbsp;
                  <h6> Manufacturer’s <br /> <span>warranty</span></h6>
                </div>
              </div>
              <div className="col-xs-6 col-sm-3">
                <div className="item d-flex">
                  <i className="bi bi-arrow-repeat"/> &nbsp; &nbsp;
                  <h6> Full refund <br /> <span>guarantee</span> </h6>
                </div>
              </div>
            </div>
          </div>
        </div>
        
            <div className="subscribe">
            <div className="container align-center">
            <h1 className="h3 upp">Join our newsletter</h1>
            <p>Get more information and receive special discounts for our products.</p>
            <form action="index.php" method="post">
                <div className="input-group container-fluid">
                <input type="email" name="email" defaultValue placeholder="E-mail" required className="form-control" />
                <span className="input-group-btn">
                    <button type="submit" className="btn btn-primary"> Subscribe <i className="bi bi-caret-right" /> </button>
                </span>
                </div>
            </form>
            <div className="social d-flex">
                <a href="#"><i className="bi bi-facebook"/></a>
                <a href="#"><i className="bi bi-twitter" /></a>
                <a href="#"><i className="bi bi-instagram" /></a>
                <a href="#"><i className="bi bi-linkedin" /></a>
                <a href="#"><i className="bi bi-youtube" /></a>
            </div>
            </div>
        </div>
       
       
          <div className="info">
            <p>Susankhya © 2021 <br /> Designed By <a href="www.linkedin.com/in/anil-paudel-SOGGOD" target="_blank">Anil Paudel</a></p>
          </div>
     

      </footer>

        </div>
    )
}

export default Footer
