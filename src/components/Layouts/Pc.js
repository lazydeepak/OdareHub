import React from 'react'

function Laptops() {
    return (
        <div>

<h1> PC</h1>

<div className="container">
<div className="row">
   <div className="col-md">
          
      <div className="card" style={{width: '18rem'}}>
      <img src="lap1.jpg" className="card-img-top" alt="Gaming PC" />
      <div className="card-body">
      <h5 className="card-title">Gaming PC</h5>
      <p className="card-text">Get the best deal on Gaming Mice and test your gaming skills with the newly launched products</p>
      <a href="#" className="btn btn-primary">Check Out!</a>
      </div>
      </div>

   </div>
      
   <div className="col-md">
          
      <div className="card" style={{width: '18rem'}}>
      <img src="lap2.jpg" className="card-img-top" alt="Business PC" />
      <div className="card-body">
      <h5 className="card-title">Business PC</h5>
      <p className="card-text">Some quick example text to build on the card title and make up the bulk of the card's content.</p>
      <a href="#" className="btn btn-primary">Check Out!</a>
      </div>
      </div>

   </div>
      <div className="col-md">
          
      <div className="card" style={{width: '18rem'}}>
      <img src="lap3.jpg" className="card-img-top" alt="Personal PC" />
      <div className="card-body">
      <h5 className="card-title">Personal PC</h5>
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

export default Laptops
