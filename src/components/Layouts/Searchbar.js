import React from 'react';
import {Link} from 'react-router-dom';

function Searchbar() {
    return (
        <div>
            
            <div className="container-fluid bg-info">
            <div className="row navbar">
                <div className="col-md-2">
                <Link className="navbar-brand" to=""> Logo </Link>
                </div>
                <div className="col-md-7">
                <form className="d-flex">
        <input className="form-control me-2" type="search" placeholder="Search" aria-label="Search"/>
        <button className="btn " type="submit"><i className="bi bi-search"></i></button>
      </form>
                </div>
                <div className="col-md-3">
                <ul className="list-unstyled text-small d-flex justify-content-end">
          <li><Link className="link-secondary px-2 text-white" to="#">
              
          <button type="button" className="btn btn-primary" data-bs-toggle="modal" data-bs-target="#staticBackdrop">
            <i className="bi bi-heart-fill"></i>
        </button>
       
        <div className="modal fade" id="staticBackdrop" data-bs-backdrop="static" data-bs-keyboard="false" tabIndex={-1} aria-labelledby="staticBackdropLabel" aria-hidden="true">
          <div className="modal-dialog">
            <div className="modal-content">
              <div className="modal-header">
                <h5 className="modal-title" id="staticBackdropLabel">My Wishlist</h5>
                <button type="button" className="btn-close" data-bs-dismiss="modal" aria-label="Close" />
              </div>
              <div className="modal-body">
                
              <div className="list-group">
        <Link href="#" className="list-group-item list-group-item-action active" aria-current="true">
          <div className="d-flex w-100 justify-content-between">
            <h5 className="mb-1">List group item heading</h5>
            <small>3 days ago</small>
          </div>
          <p className="mb-1">Donec id elit non mi porta gravida at eget metus. Maecenas sed diam eget risus varius blandit.</p>
          <small>Donec id elit non mi porta.</small>
        </Link>
        <Link href="#" className="list-group-item list-group-item-action">
          <div className="d-flex w-100 justify-content-between">
            <h5 className="mb-1">List group item heading</h5>
            <small className="text-muted">3 days ago</small>
          </div>
          <p className="mb-1">Donec id elit non mi porta gravida at eget metus. Maecenas sed diam eget risus varius blandit.</p>
          <small className="text-muted">Donec id elit non mi porta.</small>
        </Link>
        <Link href="#" className="list-group-item list-group-item-action">
          <div className="d-flex w-100 justify-content-between">
            <h5 className="mb-1">List group item heading</h5>
            <small className="text-muted">3 days ago</small>
          </div>
          <p className="mb-1">Donec id elit non mi porta gravida at eget metus. Maecenas sed diam eget risus varius blandit.</p>
          <small className="text-muted">Donec id elit non mi porta.</small>
        </Link>
      </div>

              </div>
            </div>
          </div>
        </div>

              
              </Link></li> &nbsp;
         
              
          <button type="button" className="btn btn-primary" data-bs-toggle="modal" data-bs-target="#exampleModal">
      <i class="bi bi-people-fill"></i>
      </button>
      
      <div className="modal fade" id="exampleModal" tabIndex={-1} aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div className="modal-dialog">
          <div className="modal-content">
            <div className="modal-header">
              <h5 className="modal-title" id="exampleModalLabel">Login Form</h5>
              <button type="button" className="btn-close" data-bs-dismiss="modal" aria-label="Close" />
            </div>
            <div className="modal-body">
              
            <form className="px-4 py-3">
          <div className="form-group">
            <label htmlFor="exampleDropdownFormEmail1">Email address</label>
            <input type="email" className="form-control" id="exampleDropdownFormEmail1" placeholder="email@example.com" />
          </div>
          <div className="form-group">
            <label htmlFor="exampleDropdownFormPassword1">Password</label>
            <input type="password" className="form-control" id="exampleDropdownFormPassword1" placeholder="Password" />
          </div>
          <div className="form-check">
            <input type="checkbox" className="form-check-input" id="dropdownCheck" />
            <label className="form-check-label" htmlFor="dropdownCheck">
              Remember me
            </label>
          </div>
          <button type="submit" className="btn btn-primary">Sign in</button>
        </form>
        <div className="dropdown-divider" />
        <Link className="dropdown-item" href="#">New around here? <Link href="">Sign up</Link> </Link>
        <Link className="dropdown-item" href="#">Forgot password?</Link>
      </div>

          </div>
          </div>
          </div>

              
               &nbsp;
          <li><Link className="link-secondary  px-2 text-white" to="/Cart"><i class="bi bi-cart4"></i></Link></li>
          
        </ul>
                </div>
            </div>
            </div>

        </div>
    )
}

export default Searchbar
