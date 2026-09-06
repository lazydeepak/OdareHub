import React from 'react'
import {BrowserRouter,Switch,Route} from 'react-router-dom'
import Aboutus from './components/pages/Aboutus'
import Contactus from './components/pages/Contactus'
import Home from './components/pages/Home'
import Myaccount from './components/pages/Myaccount'
import Services from './components/pages/Services'
function Routes() {
    return (
        <BrowserRouter> 
        <Switch>
            <Route exact path='/' component={Home}/>
            <Route path='/home' component={Home}/>
            <Route path='/services' component={Services}/>
            <Route path='/aboutus' component={Aboutus}/>
            <Route path='/contactus' component={Contactus}/>
            <Route path='/myaccount' component={Myaccount}/>
          

            </Switch>
        </BrowserRouter>
    )
}

export default Routes
