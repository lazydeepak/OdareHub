import React from 'react'
import {NavLink} from 'react-router-dom';
function Navbar() {
    return (
        <div className="navmain" style={{padding:'5px' }}>
         
          <ul className="nav justify-content-center">
          <li className="nav-item">
            <NavLink exact activeClassName="active-link" className="nav-link" to="./">home</NavLink>
          </li>
          <li className="nav-item">
            <NavLink activeClassName="active-link" className="nav-link" to="./services">Services</NavLink>
          </li>
          <li className="nav-item">
            <NavLink activeClassName="active-link" className="nav-link" to="./aboutus">About Us</NavLink>
          </li>
          <li className="nav-item">
            <NavLink activeClassName="active-link" className="nav-link" to="./contactus">Contact Us</NavLink>
          </li>
          <li className="nav-item">
            <NavLink activeClassName="active-link" className="nav-link" to="./myaccount">My Account</NavLink>
          </li>
        </ul>

        </div>
    )
}

export default Navbar
