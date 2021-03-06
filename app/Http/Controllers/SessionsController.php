<?php

namespace Javan\Http\Controllers;

use Illuminate\Http\Request;
use Javan\Reservation;

class SessionsController extends Controller
{
	protected $user;

	/**
	 * SessionsController constructor.
	 */
	public function __construct()
	{
		$this->middleware(['auth', 'active']);
		$this->user = auth()->user();
	}

	/**
	 * Display the specified resource.
	 *
	 * @return mixed
	 */
	public function show()
	{
		return view('user.profile', ['user' => $this->user]);
	}

	/**
	 * Show the form for editing the specified resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function edit()
	{
		return view('user.member-edit-profile', ['user' => $this->user]);
	}

	/**
	 * Update the specified resource in storage.
	 *
	 * @param  \Illuminate\Http\Request $request
	 * @return \Illuminate\Http\Response
	 */
	public function update(Request $request)
	{
		$this->validate($request, [
			'name'      => 'required',
			'email'     => 'required|email',
			'address'   => 'required',
			'city'      => 'required',
			'post_code' => 'required',
			'phone'     => 'required|numeric',
		]);

		if ( ! trim($request->password)) {
			$this->user->update($request->except('password'));
		} else {
			$this->user->update($request->all());
		}
		flash()->success('Success', 'Profile has been updated successfully');

		return back();
	}

	/**
	 * @return \Illuminate\Contracts\View\Factory
	 * @throws \Exception
	 * \Illuminate\Http\RedirectResponse
	 * \Illuminate\View\View
	 */
	public function reservations()
	{
		if ($this->user->reservations->isEmpty()) {
			return redirect()->route('reservations.create');
		}

		Reservation::cancelOldReservations();

		return view('reservations.index', ['reservations' => $this->user->reservations()->paginate(20)]);
	}

	/**
	 * @return \Illuminate\Contracts\View\Factory
	 * @throws \Exception
	 * \Illuminate\Http\RedirectResponse
	 * \Illuminate\View\View
	 */
	public function bookings()
	{
		if ($this->user->bookings->isEmpty()) {
			return redirect()->route('music');
		}

		$sortBy    = request()->get('sortBy');
		$direction = request()->get('direction');
		$params    = compact('sortBy', 'direction');

		if ($sortBy && $direction) {
			$bookings = $this->user->bookings()->with('event')
			                       ->orderBy($params['sortBy'], $params['direction'])->paginate(20);
		} else {
			$bookings = $this->user->bookings()->with('event')
			                       ->latest()->paginate(20);
		}

		return view('bookings.index', compact('bookings'));
	}

	/**
	 * @return \Illuminate\Contracts\View\Factory
	 * \Illuminate\Http\RedirectResponse
	 * \Illuminate\Routing\Redirector|\Illuminate\View\View
	 */
	public function orders()
	{
		if ($this->user->shoppingCarts->isEmpty()) {
			return redirect()->route('menu');
		}

		$sortBy    = request()->get('sortBy');
		$direction = request()->get('direction');
		$params    = compact('sortBy', 'direction');

		if ($sortBy && $direction) {
			$carts = $this->user->shoppingCarts()->orderBy($params['sortBy'], $params['direction'])->paginate(20);
		} else {
			$carts = $this->user->shoppingCarts()->latest()->paginate(20);
		}

		$carts->transform(function($cart) {
			$cart->orders = unserialize($cart->orders);

			return $cart;
		});

		return view('cart.index', compact('carts'));
	}
}
