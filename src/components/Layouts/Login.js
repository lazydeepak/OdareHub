import React from 'react'

function Login() {
    return (
        <div>

                
      <button type="button" className="btn btn-primary" data-bs-toggle="modal" data-bs-target="#exampleModal">
      <i class="bi bi-people-fill"></i>
      </button>
      
      <div className="modal fade" id="exampleModal" tabIndex={-1} aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div className="modal-dialog">
          <div className="modal-content">
            <div className="modal-header">
              <h5 className="modal-title" id="exampleModalLabel">Modal title</h5>
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
        <a className="dropdown-item" href="#">New around here? Sign up</a>
        <a className="dropdown-item" href="#">Forgot password?</a>
      </div>

            <div className="modal-footer">
        <button type="button" className="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" className="btn btn-primary">Save changes</button>
      </div>
          </div>
          </div>
          </div>

           
        </div>
    )
}

export default Login
