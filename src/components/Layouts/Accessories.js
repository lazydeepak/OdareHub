import React from 'react'
import '../Layouts/Accessories.css'

function Accessories() {
    return (
        <div>
               <h1> Accessories</h1>

          <div className="container">
          <div className="row">
             <div className="col-md">
                    
                <div className="card" style={{width: '18rem'}}>
                <img src="ac1.jpg" className="card-img-top" alt="Gaming Mouse" />
                <div className="card-body">
                <h5 className="card-title">Gaming Mouse</h5>
                <p className="card-text">Get the best deal on Gaming Mice and test your gaming skills with the newly launched products</p>
                <a href="#" className="btn btn-primary">Check Out!</a>
                </div>
                </div>

             </div>
                
             <div className="col-md">
                    
                <div className="card" style={{width: '18rem'}}>
                <img src="ac2.jpg" className="card-img-top" alt="Gaming Keyboard" />
                <div className="card-body">
                <h5 className="card-title">Gaming Keyboard</h5>
                <p className="card-text">Some quick example text to build on the card title and make up the bulk of the card's content.</p>
                <a href="#" className="btn btn-primary">Check Out!</a>
                </div>
                </div>

             </div>
                <div className="col-md">
                    
                <div className="card" style={{width: '18rem'}}>
                <img src="ac3.jpg" className="card-img-top" alt="Gaming Headphones" />
                <div className="card-body">
                <h5 className="card-title">Gaming Headphones</h5>
                <p className="card-text">Some quick example text to build on the card title and make up the bulk of the card's content.</p>
                <a href="#" className="btn btn-primary">Check Out!</a>
                </div>
                </div>

                </div>
                
          </div>
          </div>
      </div>
               
    )
}

export default Accessories
